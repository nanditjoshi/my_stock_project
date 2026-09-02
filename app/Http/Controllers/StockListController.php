<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class StockListController extends Controller
{
    public function index(Request $request)
    {
        $tables = $this->getTables();
        $selectedTable = $request->query('table');
        $date = $request->query('date');
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $sort = $request->query('sort', 'volume');
        $direction = strtolower($request->query('direction', 'asc'));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';
        $columns = [];
        $rows = collect();
        $querySql = null;
        $hasCreatedAt = false;
        $syncSummary = null;
        $buyAlertSymbols = [];

        if ($selectedTable && Schema::hasTable($selectedTable)) {
            $tableColumns = Schema::getColumnListing($selectedTable);
            $hasCreatedAt = in_array('created_at', $tableColumns, true);
            $columns = $tableColumns;
            $requestedStockColumns = $this->getRequestedStockColumns($selectedTable);

            if ($requestedStockColumns !== []) {
                $columns = $requestedStockColumns;
            }

            $query = DB::table($selectedTable);
            $queryColumns = $columns;

            // The star state is needed by the view but is not a regular stock
            // data column, so keep it out of the visible table columns.
            if (in_array('is_whatched', $tableColumns, true)) {
                $queryColumns[] = 'is_whatched';
            }

            if ($queryColumns !== []) {
                $query->select(array_values(array_unique($queryColumns)));
            }

            if ($date && $hasCreatedAt) {
                $query->whereDate('created_at', $date);
            }

            $querySql = $query->toSql();
            $sourceRows = $query->get();
            $rows = $this->addSymbolOccurrenceCounts(
                $this->combineDuplicateSymbols($sourceRows, $queryColumns),
                $sourceRows
            );
            $buyAlertSymbols = $this->getBuyAlertSymbols($selectedTable, $rows, $date);

            if (in_array($sort, $columns, true)) {
                $rows = $this->sortRows(
                    $rows,
                    $sort,
                    $direction,
                    $sort === 'volume' || $this->columnLooksNumeric($selectedTable, $sort)
                );
            }

            // Only the explicit Apply Filter action writes to the watch list.
            // Selecting a table still refreshes the list without making API calls.
            if ($request->query('action') === 'apply') {
                $syncSummary = $this->syncRowsToWatchList($rows);
            }
        }

        return view('stock-list', compact('tables', 'selectedTable', 'date', 'hasCreatedAt', 'columns', 'rows', 'sort', 'direction', 'querySql', 'syncSummary', 'buyAlertSymbols'));
    }

    public function watchList(Request $request)
    {
        $tables = $this->getTables();
        $selectedTable = $request->query('table', '20_cross_50');
        $tableExists = Schema::hasTable($selectedTable);
        $tableColumns = $tableExists ? Schema::getColumnListing($selectedTable) : [];

        $todayTop = $tableExists ? $this->getTopVolumeRows($selectedTable, 'day') : [];
        $weekTop = $tableExists ? $this->getTopVolumeRows($selectedTable, 'week') : [];
        $monthTop = $tableExists ? $this->getTopVolumeRows($selectedTable, 'month') : [];
        $twoWeeksTop = $tableExists ? $this->getTopVolumeRows($selectedTable, 'two_weeks') : [];
        $quarterTop = $tableExists ? $this->getTopVolumeRows($selectedTable, 'quarter') : [];
        $halfYearTop = $tableExists ? $this->getTopVolumeRows($selectedTable, 'half_year') : [];
        $yearTop = $tableExists ? $this->getTopVolumeRows($selectedTable, 'year') : [];
        $tableSupportsWatched = in_array($selectedTable, $this->watchedTables(), true)
            && in_array('is_whatched', $tableColumns, true);

        return view('watch-list', compact(
            'tables',
            'selectedTable',
            'tableColumns',
            'todayTop',
            'weekTop',
            'monthTop',
            'twoWeeksTop',
            'quarterTop',
            'halfYearTop',
            'yearTop',
            'tableSupportsWatched'
        ));
    }

    /**
     * Show watched records from the two stock signal tables.
     */
    public function dashboard()
    {
        $watchedRecords = collect();
        $smData = $this->getSmData();

        foreach ($this->watchedTables() as $table) {
            if (!Schema::hasTable($table)
                || !Schema::hasColumn($table, 'symbol')
                || !Schema::hasColumn($table, 'is_whatched')) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $priceColumn = in_array('price', $columns, true)
                ? 'price'
                : (in_array('close', $columns, true) ? 'close' : null);

            $query = DB::table($table)
                ->select(['symbol', 'is_whatched'])
                ->where('is_whatched', true);

            if ($priceColumn !== null) {
                $query->addSelect($priceColumn);
            }

            if (in_array('created_at', $columns, true)) {
                $query->addSelect('created_at');
            }

            $watchedRecords = $watchedRecords->concat($query->get()->map(function ($row) use ($table, $priceColumn) {
                return (object) [
                    'table' => $table,
                    'symbol' => $row->symbol,
                    'price' => $priceColumn === null ? null : $row->{$priceColumn},
                    'is_whatched' => (bool) $row->is_whatched,
                    'recorded_at' => $row->created_at ?? null,
                ];
            }));
        }

        // A symbol can appear several times in historical source rows or in
        // both source tables. Keep only its newest watched record.
        $watchedRecords = $watchedRecords
            ->sortByDesc('recorded_at')
            ->unique(function ($record) {
                return strtoupper(trim((string) $record->symbol));
            })
            ->values();

        return view('dashboard', compact('watchedRecords', 'smData'));
    }

    /**
     * Download every 30w EMA cross record in a symbol-by-date change matrix.
     */
    public function downloadSmData()
    {
        $smData = $this->getSmData();
        $filename = 'smdata-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($smData) {
            $handle = fopen('php://output', 'w');
            $dateHeaders = $smData['dates']->map(function ($date) {
                return Carbon::parse($date)->format('dm');
            })->all();
            fputcsv($handle, array_merge(['symbol', 'Close / Price'], $dateHeaders));

            foreach ($smData['rows'] as $row) {
                $values = [$row->symbol, $row->price];
                foreach ($smData['dates'] as $date) {
                    $values[] = $row->changes[$date] ?? '';
                }
                fputcsv($handle, $values);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Refresh every saved snapshot from the stock service. A 503 response is
     * retried against the other supported exchange for the same symbol.
     */
    public function syncStockSnapshots()
    {
        if (!Schema::hasTable('stock_snapshots')) {
            return redirect()->route('dashboard')->with('error', 'The stock snapshots table does not exist.');
        }

        $snapshots = DB::table('stock_snapshots')
            ->select(['id', 'symbol', 'exchange'])
            ->whereNotNull('symbol')
            ->where(function ($query) {
                $query->whereNull('updated_at')
                    ->orWhereDate('updated_at', '!=', now()->toDateString());
            })
            ->get();
        $synced = 0;
        $skipped = 0;

        foreach ($snapshots as $snapshot) {
            $symbol = trim((string) $snapshot->symbol);
            if ($symbol === '') {
                $skipped++;
                continue;
            }

            $exchange = strtoupper(trim((string) $snapshot->exchange));
            $exchange = in_array($exchange, ['NSE', 'BSE'], true) ? $exchange : 'NSE';

            try {
                $response = Http::acceptJson()
                    ->timeout(10)
                    ->get('http://127.0.0.1:8001/api/v1/stocks', [
                        'symbol' => $symbol,
                        'exchange' => $exchange,
                    ]);

                if ($response->status() === 503) {
                    $exchange = $exchange === 'NSE' ? 'BSE' : 'NSE';
                    $response = Http::acceptJson()
                        ->timeout(10)
                        ->get('http://127.0.0.1:8001/api/v1/stocks', [
                            'symbol' => $symbol,
                            'exchange' => $exchange,
                        ]);
                }

                if ($response->failed() || !$this->updateStockSnapshot($snapshot->id, $exchange, $response->json())) {
                    $skipped++;
                    continue;
                }

                $synced++;
            } catch (\Throwable $exception) {
                $skipped++;
            }
        }

        return redirect()->route('dashboard')->with('snapshotSyncSummary', compact('synced', 'skipped'));
    }

    /**
     * Toggle a symbol's watched state in one of the approved signal tables.
     */
    public function toggleWatched(Request $request)
    {
        $validated = $request->validate([
            'table' => ['required', 'string', 'in:' . implode(',', $this->watchedTables())],
            'symbol' => ['required', 'string', 'max:255'],
            'is_whatched' => ['required', 'boolean'],
        ]);

        $table = $validated['table'];
        if (!Schema::hasTable($table)
            || !Schema::hasColumn($table, 'symbol')
            || !Schema::hasColumn($table, 'is_whatched')) {
            return response()->json(['message' => 'This table does not support watched stocks.'], 422);
        }

        $values = ['is_whatched' => (bool) $validated['is_whatched']];
        if (Schema::hasColumn($table, 'updated_at')) {
            $values['updated_at'] = now();
        }

        DB::table($table)->where('symbol', trim($validated['symbol']))->update($values);

        return response()->json([
            'table' => $table,
            'symbol' => trim($validated['symbol']),
            'is_whatched' => $values['is_whatched'],
        ]);
    }

    public function storeWatchList(Request $request)
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric'],
            'current_price' => ['required', 'numeric'],
            '9ema' => ['required', 'numeric'],
            '21ema' => ['required', 'numeric'],
            '10wema' => ['required', 'numeric'],
            '30wema' => ['required', 'numeric'],
        ]);

        DB::table('whatch_list')->insert([
            'symbol' => $validated['symbol'],
            'price' => $validated['price'],
            'current_price' => $validated['current_price'],
            '9ema' => $validated['9ema'],
            '21ema' => $validated['21ema'],
            '10wema' => $validated['10wema'],
            '30wema' => $validated['30wema'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Stock added to the watch list.');
    }

    /**
     * Retrieve the latest indicators for the stock selected in the add modal.
     */
    public function syncStock(Request $request)
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:255'],
        ]);

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get('http://127.0.0.1:8001/api/v1/stocks', [
                    'symbol' => trim($validated['symbol']),
                    'exchange' => 'NSE',
                ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Unable to connect to the stock sync service.',
            ], 502);
        }

        if ($response->failed()) {
            return response()->json([
                'message' => 'The stock sync service could not retrieve this symbol.',
            ], $response->status() >= 400 && $response->status() < 600 ? $response->status() : 502);
        }

        return response()->json($response->json());
    }

    /**
     * Sync filtered stock rows into the watch list. Existing symbols are updated
     * so applying the same filter twice does not create duplicates.
     */
    protected function syncRowsToWatchList($rows): array
    {
        $synced = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $symbol = trim((string) ($row->symbol ?? ''));

            if ($symbol === '') {
                $skipped++;
                continue;
            }

            try {
                $response = Http::acceptJson()
                    ->timeout(10)
                    ->get('http://127.0.0.1:8001/api/v1/stocks', [
                        'symbol' => $symbol,
                        'exchange' => 'NSE',
                    ]);

                // Some symbols are only available through BSE. Retry there
                // only when the NSE service explicitly reports a 503.
                if ($response->status() === 503) {
                    $response = Http::acceptJson()
                        ->timeout(10)
                        ->get('http://127.0.0.1:8001/api/v1/stocks', [
                            'symbol' => $symbol,
                            'exchange' => 'BSE',
                        ]);
                }

                if ($response->failed()) {
                    $skipped++;
                    continue;
                }

                $payload = $response->json();
                $data = is_array($payload) ? ($payload['data'] ?? $payload['result'] ?? $payload) : $payload;
                $data = is_array($data) && isset($data[0]) && is_array($data[0]) ? $data[0] : $data;

                if (!is_array($data)) {
                    $skipped++;
                    continue;
                }

                $currentPrice = $this->indicatorValue($data, ['current_price', 'currentPrice', 'price', 'close']);
                $ema9 = $this->indicatorValue($data, ['9ema', 'ema_9', 'ema9', '9_ema', 'EMA9']);
                $ema21 = $this->indicatorValue($data, ['21ema', 'ema_21', 'ema21', '21_ema', 'EMA21']);
                $ema10Week = $this->indicatorValue($data, ['ema_10_week', '10wema', 'ema10week', '10_week_ema', 'ema_10w', 'EMA10W']);
                $ema30Week = $this->indicatorValue($data, ['30wema', 'ema_30_week', 'ema30week', '30_week_ema', 'ema_30w', 'EMA30W']);

                if ($currentPrice === null || $ema9 === null || $ema21 === null || $ema10Week === null || $ema30Week === null) {
                    $skipped++;
                    continue;
                }

                $sourcePrice = $this->numberValue($row->price ?? $row->close ?? 0) ?? 0;
                $values = [
                    'price' => $sourcePrice,
                    'current_price' => $currentPrice,
                    '9ema' => $ema9,
                    '21ema' => $ema21,
                    '10wema' => $ema10Week,
                    '30wema' => $ema30Week,
                    'updated_at' => now(),
                ];

                $exists = DB::table('whatch_list')->where('symbol', $symbol)->exists();
                if ($exists) {
                    DB::table('whatch_list')->where('symbol', $symbol)->update($values);
                } else {
                    $values['symbol'] = $symbol;
                    $values['created_at'] = now();
                    DB::table('whatch_list')->insert($values);
                }

                $synced++;
            } catch (\Throwable $exception) {
                $skipped++;
            }
        }

        return compact('synced', 'skipped');
    }

    protected function indicatorValue(array $data, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $this->numberValue($data[$key]);
            }
        }

        return null;
    }

    protected function numberValue($value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalised = str_replace(',', '', trim($value));
            return is_numeric($normalised) ? (float) $normalised : null;
        }

        return null;
    }

    protected function getRequestedStockColumns(string $table): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);
        $requestedColumns = ['stock_name', 'symbol', 'price', 'close', 'volume'];

        $availableColumns = array_values(array_filter($requestedColumns, static function (string $column) use ($columns): bool {
            return in_array($column, $columns, true);
        }));

        return $availableColumns;
    }

    protected function getTopVolumeRows(string $table, string $period): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);
        if (!in_array('volume', $columns, true) || !in_array('created_at', $columns, true)) {
            return [];
        }

        $query = DB::table($table)->select('*');

        if ($period === 'day') {
            $query->whereDate('created_at', now()->subDay()->toDateString());
            //$query->whereDate('created_at', now()->toDateString());
        } elseif ($period === 'week') {
            $query->where('created_at', '>=', now()->startOfWeek()->toDateTimeString());
        } elseif ($period === 'month') {
            $query->where('created_at', '>=', now()->startOfMonth()->toDateTimeString());
        } elseif ($period === 'two_weeks') {
            $query->where('created_at', '>=', now()->subWeeks(2)->toDateTimeString());
        } elseif ($period === 'quarter') {
            $query->where('created_at', '>=', now()->startOfQuarter()->toDateTimeString());
        } elseif ($period === 'half_year') {
            $query->where('created_at', '>=', now()->subMonths(6)->toDateTimeString());
        } elseif ($period === 'year') {
            $query->where('created_at', '>=', now()->startOfYear()->toDateTimeString());
        }

        $rows = $this->combineDuplicateSymbols($query->get(), $columns)
            ->sortByDesc(function ($row) {
                return $this->numberValue($row->volume ?? null) ?? 0;
            }, SORT_NUMERIC)
            ->take(5)
            ->values()
            ->all();

        $this->addLatestPrices($table, $rows, $columns);

        foreach ($rows as $row) {
            if (isset($row->volume) && is_string($row->volume)) {
                $row->volume = (int) preg_replace('/[^0-9]/', '', $row->volume);
            }
        }

        return $rows;
    }

    /**
     * Replace each displayed row's price with the most recent recorded price
     * for that symbol. Volume remains aggregated for the selected period, but
     * prices always reflect the latest row in the selected table.
     */
    protected function addLatestPrices(string $table, array $rows, array $columns): void
    {
        $priceColumn = in_array('close', $columns, true)
            ? 'close'
            : (in_array('price', $columns, true) ? 'price' : null);

        if ($rows === [] || $priceColumn === null || !in_array('symbol', $columns, true)) {
            return;
        }

        $symbols = collect($rows)
            ->pluck('symbol')
            ->map(static fn ($symbol) => trim((string) $symbol))
            ->filter()
            ->unique()
            ->values();

        if ($symbols->isEmpty()) {
            return;
        }

        $latestRows = DB::table($table)
            ->select(['symbol', $priceColumn, 'created_at'])
            ->whereIn('symbol', $symbols)
            ->whereNotNull($priceColumn)
            ->orderByDesc('created_at')
            ->get()
            ->unique(static fn ($row) => trim((string) $row->symbol))
            ->keyBy(static fn ($row) => trim((string) $row->symbol));

        foreach ($rows as $row) {
            $symbol = trim((string) ($row->symbol ?? ''));
            $latestRow = $latestRows->get($symbol);

            if ($latestRow !== null) {
                $row->{$priceColumn} = $latestRow->{$priceColumn};
            }
        }
    }

    /**
     * Return symbols that have appeared on at least four consecutive calendar
     * days, whose volume on the selected (or latest) day exceeds 65,000, and
     * whose historical average `chang` value is positive.
     */
    protected function getBuyAlertSymbols(string $table, $rows, ?string $selectedDate): array
    {
        $columns = Schema::getColumnListing($table);
        
        if (!in_array('symbol', $columns, true)
            || !in_array('volume', $columns, true)
            || !in_array('change', $columns, true)
            || !in_array('created_at', $columns, true)) {
            return [];
        }

        $symbols = $rows->pluck('symbol')
            ->map(static function ($symbol) {
                return trim((string) $symbol);
            })
            ->filter()
            ->unique()
            ->values();

        if ($symbols->isEmpty()) {
            return [];
        }

        $history = DB::table($table)
            ->select(['symbol', 'volume', 'change', 'created_at'])
            ->whereIn('symbol', $symbols)
            ->whereNotNull('created_at')
            ->get()
            ->groupBy(static function ($row) {
                return trim((string) $row->symbol);
            });

        return $history->filter(function ($symbolRows) use ($selectedDate) {
            //var_dump((float) rtrim($symbolRows[0]->change, '%'));die;
            $averageChange = $symbolRows
                ->map(function ($row) {
                    //return $this->numberValue($row->change ?? null);
                    return (float) rtrim($row->change ?? null);
                })
                ->filter(static function ($change) {
                    return $change !== null;
                })
                ->avg();
            
            if ($averageChange === null || $averageChange <= 0) {
                return false;
            }

            $dailyVolumes = $symbolRows->groupBy(function ($row) {
                return Carbon::parse($row->created_at)->toDateString();
            })->map(function ($dayRows) {
                return $dayRows->sum(function ($row) {
                    return $this->numberValue($row->volume ?? null) ?? 0;
                });
            });

            $targetDate = $selectedDate ?: $dailyVolumes->keys()->sortDesc()->first();

            if (!$targetDate || ($dailyVolumes->get($targetDate) ?? 0) <= 65000) {
                return false;
            }

            $currentDate = Carbon::parse($targetDate)->startOfDay();

            for ($day = 1; $day < 5; $day++) {
                $currentDate = $currentDate->copy()->subDay();

                if (!$dailyVolumes->has($currentDate->toDateString())) {
                    return false;
                }
            }

            return true;
        })->keys()->all();
    }

    /**
     * Keep one row per symbol while preserving the first row's non-volume data
     * and adding together all matching volume values.
     */
    protected function combineDuplicateSymbols($rows, array $columns)
    {
        if (!in_array('symbol', $columns, true) || !in_array('volume', $columns, true)) {
            return $rows;
        }

        return $rows->values()
            ->groupBy(function ($row, $index) {
                $symbol = trim((string) ($row->symbol ?? ''));

                return $symbol !== '' ? $symbol : '__row_' . $index;
            })
            ->map(function ($symbolRows) {
                $row = clone $symbolRows->first();
                $row->volume = $symbolRows->sum(function ($symbolRow) {
                    return $this->numberValue($symbolRow->volume ?? null) ?? 0;
                });

                if (isset($row->is_whatched)) {
                    $row->is_whatched = $symbolRows->contains(function ($symbolRow) {
                        return (bool) ($symbolRow->is_whatched ?? false);
                    });
                }

                return $row;
            })
            ->values();
    }

    /**
     * Add the number of matching symbols from the currently displayed table
     * data. The count remains available after duplicate rows are combined.
     */
    protected function addSymbolOccurrenceCounts($rows, $sourceRows)
    {
        $counts = $sourceRows
            ->map(static function ($row): string {
                return trim((string) ($row->symbol ?? ''));
            })
            ->filter()
            ->countBy();

        return $rows->map(function ($row) use ($counts) {
            $symbol = trim((string) ($row->symbol ?? ''));
            $row->symbol_occurrence_count = $symbol === '' ? 0 : ($counts[$symbol] ?? 0);

            return $row;
        });
    }

    protected function sortRows($rows, string $column, string $direction, bool $numeric)
    {
        $value = function ($row) use ($column, $numeric) {
            if ($numeric) {
                return $this->numberValue($row->{$column} ?? null) ?? 0;
            }

            return strtolower((string) ($row->{$column} ?? ''));
        };

        $options = $numeric ? SORT_NUMERIC : SORT_NATURAL | SORT_FLAG_CASE;

        return $direction === 'desc'
            ? $rows->sortByDesc($value, $options)->values()
            : $rows->sortBy($value, $options)->values();
    }

    protected function columnLooksNumeric(string $table, string $column): bool
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return false;
        }

        $type = Schema::getColumnType($table, $column);

        return in_array($type, ['bigint', 'integer', 'int', 'decimal', 'float', 'double'], true);
    }

    protected function getTables(): array
    {
        $tables = DB::select('SHOW TABLES');
        $list = [];

        foreach ($tables as $table) {
            $value = (array) $table;
            $list[] = reset($value);
        }

        return $list;
    }

    protected function watchedTables(): array
    {
        return ['20_cross_50', '30w_ema_cross'];
    }

    /**
     * Build the SM data export/preview. Each date column contains the change
     * recorded for a symbol on that date, and price is taken from its newest row.
     */
    protected function getSmData(): array
    {
        $table = '30w_ema_cross';
        $empty = ['dates' => collect(), 'rows' => collect(), 'recordCount' => 0];

        if (!Schema::hasTable($table)) {
            return $empty;
        }

        $columns = Schema::getColumnListing($table);
        $requiredColumns = ['symbol', 'close', 'change', 'created_at'];
        if (array_diff($requiredColumns, $columns) !== []) {
            return $empty;
        }

        $selectColumns = ['symbol', 'close', 'change', 'created_at'];
        $hasWatchedColumn = in_array('is_whatched', $columns, true);
        if ($hasWatchedColumn) {
            $selectColumns[] = 'is_whatched';
        }

        $records = DB::table($table)
            ->select($selectColumns)
            ->whereNotNull('symbol')
            ->whereNotNull('created_at')
            ->orderBy('created_at')
            ->get()
            ->map(function ($record) {
                $record->record_date = Carbon::parse($record->created_at)->toDateString();
                return $record;
            });

        $dates = $records->pluck('record_date')->unique()->sortDesc()->values();
        $rows = $records->groupBy(function ($record) {
            return trim((string) $record->symbol);
        })->filter(function ($symbolRows, $symbol) {
            return $symbol !== '';
        })->map(function ($symbolRows, $symbol) {
            $latestRecord = $symbolRows->sortByDesc('created_at')->first();
            $changes = $symbolRows->mapWithKeys(function ($record) {
                return [$record->record_date => $record->change];
            });

            return (object) [
                'symbol' => $symbol,
                'price' => $latestRecord->close,
                'changes' => $changes,
                'is_whatched' => $symbolRows->contains(function ($record) {
                    return (bool) ($record->is_whatched ?? false);
                }),
            ];
        })->sortBy('symbol')->values();

        return compact('dates', 'rows') + ['recordCount' => $records->count()];
    }

    /**
     * Store the fields supplied by the stock service for one snapshot record.
     */
    protected function updateStockSnapshot(int $id, string $exchange, $payload): bool
    {
        $data = is_array($payload) ? ($payload['data'] ?? $payload['result'] ?? $payload) : null;
        $data = is_array($data) && isset($data[0]) && is_array($data[0]) ? $data[0] : $data;

        if (!is_array($data)) {
            return false;
        }

        $values = [
            'exchange' => $exchange,
            'fetched_at' => now(),
            'source_payload' => json_encode($payload),
            'updated_at' => now(),
        ];
        $stringFields = [
            'company_name' => ['company_name', 'companyName', 'stock_name', 'name', 'longName', 'shortName'],
            'sector' => ['sector'],
        ];
        $numericFields = [
            'current_price' => ['current_price', 'currentPrice', 'price', 'close'],
            'previous_close' => ['previous_close', 'previousClose'],
            'price_change' => ['price_change', 'priceChange', 'change'],
            'price_change_percent' => ['price_change_percent', 'priceChangePercent', 'change_percent', 'changePercent'],
            'ema_9' => ['ema_9', 'ema9', '9ema', '9_ema', 'EMA9'],
            'ema_21' => ['ema_21', 'ema21', '21ema', '21_ema', 'EMA21'],
            'ema_10_week' => ['ema_10_week', 'ema10week', '10wema', '10_week_ema', 'ema_10w', 'EMA10W'],
            'ema_30_week' => ['ema_30_week', 'ema30week', '30wema', '30_week_ema', 'ema_30w', 'EMA30W'],
        ];

        foreach ($stringFields as $column => $keys) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $data) && $data[$key] !== null) {
                    $values[$column] = (string) $data[$key];
                    break;
                }
            }
        }

        foreach ($numericFields as $column => $keys) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $data)) {
                    $number = $this->numberValue(str_replace('%', '', (string) $data[$key]));
                    if ($number !== null) {
                        $values[$column] = $number;
                    }
                    break;
                }
            }
        }

        DB::table('stock_snapshots')->where('id', $id)->update($values);

        return true;
    }
}

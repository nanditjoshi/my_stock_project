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

            if ($columns !== []) {
                $query->select($columns);
            }

            if ($date && $hasCreatedAt) {
                $query->whereDate('created_at', $date);
            }

            $querySql = $query->toSql();
            $sourceRows = $query->get();
            $rows = $this->addSymbolOccurrenceCounts(
                $this->combineDuplicateSymbols($sourceRows, $columns),
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
            'yearTop'
        ));
    }

    public function storeWatchList(Request $request)
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric'],
            'current_price' => ['required', 'numeric'],
            '9ema' => ['required', 'numeric'],
            '21ema' => ['required', 'numeric'],
            '30wema' => ['required', 'numeric'],
        ]);

        DB::table('whatch_list')->insert([
            'symbol' => $validated['symbol'],
            'price' => $validated['price'],
            'current_price' => $validated['current_price'],
            '9ema' => $validated['9ema'],
            '21ema' => $validated['21ema'],
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
                $ema30Week = $this->indicatorValue($data, ['30wema', 'ema_30_week', 'ema30week', '30_week_ema', 'ema_30w', 'EMA30W']);

                if ($currentPrice === null || $ema9 === null || $ema21 === null || $ema30Week === null) {
                    $skipped++;
                    continue;
                }

                $sourcePrice = $this->numberValue($row->price ?? $row->close ?? 0) ?? 0;
                $values = [
                    'price' => $sourcePrice,
                    'current_price' => $currentPrice,
                    '9ema' => $ema9,
                    '21ema' => $ema21,
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

        foreach ($rows as $row) {
            if (isset($row->volume) && is_string($row->volume)) {
                $row->volume = (int) preg_replace('/[^0-9]/', '', $row->volume);
            }
        }

        return $rows;
    }

    /**
     * Return symbols that have appeared on at least four consecutive calendar
     * days and whose volume on the selected (or latest) day exceeds 65,000.
     */
    protected function getBuyAlertSymbols(string $table, $rows, ?string $selectedDate): array
    {
        $columns = Schema::getColumnListing($table);

        if (!in_array('symbol', $columns, true)
            || !in_array('volume', $columns, true)
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
            ->select(['symbol', 'volume', 'created_at'])
            ->whereIn('symbol', $symbols)
            ->whereNotNull('created_at')
            ->get()
            ->groupBy(static function ($row) {
                return trim((string) $row->symbol);
            });

        return $history->filter(function ($symbolRows) use ($selectedDate) {
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

            for ($day = 1; $day < 4; $day++) {
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
}

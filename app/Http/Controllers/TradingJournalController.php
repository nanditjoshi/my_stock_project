<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TradingJournalController extends Controller
{
    public function index()
    {
        $journals = DB::table('trading_journals')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('trading-journals.index', compact('journals'));
    }

    public function searchSymbols(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:32'],
        ]);

        if (!Schema::hasTable('stock_snapshots') || !Schema::hasColumn('stock_snapshots', 'symbol')) {
            return response()->json(['symbols' => []]);
        }

        $term = trim($validated['q']);
        if (mb_strlen($term) < 2) {
            return response()->json(['symbols' => []]);
        }

        $symbols = DB::table('stock_snapshots')
            ->whereNotNull('symbol')
            ->where('symbol', 'like', '%' . $term . '%')
            ->select('symbol')
            ->distinct()
            ->orderBy('symbol')
            ->limit(20)
            ->pluck('symbol');

        return response()->json(['symbols' => $symbols]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:32'],
            'entry_date' => ['required', 'date'],
            'exit_date' => ['nullable', 'date', 'after_or_equal:entry_date'],
            'reason_of_entry' => ['nullable', 'string', 'max:10000'],
            'reason_of_exit' => ['nullable', 'string', 'max:10000'],
            'setup' => ['nullable', 'string', 'max:255'],
            'entry_price' => ['required', 'numeric', 'min:0'],
            'exit_price' => ['nullable', 'numeric', 'min:0'],
            'current_price' => ['nullable', 'numeric', 'min:0'],
            'buying_average' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'entry_image' => ['nullable', 'image', 'max:5120'],
            'exit_image' => ['nullable', 'image', 'max:5120'],
        ]);

        foreach (['entry_image', 'exit_image'] as $imageField) {
            if ($request->hasFile($imageField)) {
                $validated[$imageField] = $request->file($imageField)->store('trading-journals', 'public');
            }
        }

        $currentPrice = $validated['current_price'] ?? null;
        $buyingAverage = $validated['buying_average'] ?? null;
        if ($currentPrice !== null && $buyingAverage !== null && (float) $buyingAverage > 0) {
            $profitLossPerShare = (float) $currentPrice - (float) $buyingAverage;
            $validated['profit_loss'] = round($profitLossPerShare * (int) $validated['quantity'], 2);
            $validated['profit_loss_percentage'] = round(($profitLossPerShare / (float) $buyingAverage) * 100, 4);
        } else {
            $validated['profit_loss'] = null;
            $validated['profit_loss_percentage'] = null;
        }

        $validated['days_held'] = !empty($validated['exit_date'])
            ? \Illuminate\Support\Carbon::parse($validated['entry_date'])
                ->diffInDays(\Illuminate\Support\Carbon::parse($validated['exit_date']))
            : null;

        $validated['created_at'] = now();
        $validated['updated_at'] = now();
        DB::table('trading_journals')->insert($validated);

        return redirect()->route('trading-journals.index')->with('success', 'Trade journal entry saved.');
    }
}

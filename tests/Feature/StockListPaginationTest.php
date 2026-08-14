<?php

namespace Tests\Feature;

use App\Http\Controllers\StockListController;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockListPaginationTest extends \Tests\TestCase
{
    public function test_selected_table_can_order_volume_column_descending(): void
    {
        $table = 'stock_list_volume_sort_' . uniqid();

        Schema::dropIfExists($table);
        Schema::create($table, function ($tableBlueprint) {
            $tableBlueprint->id();
            $tableBlueprint->string('name');
            $tableBlueprint->integer('volume');
            $tableBlueprint->timestamps();
        });

        DB::table($table)->insert([
            ['name' => 'Item A', 'volume' => 10],
            ['name' => 'Item B', 'volume' => 30],
            ['name' => 'Item C', 'volume' => 20],
        ]);

        $request = Request::create('/stock-list', 'GET', [
            'table' => $table,
            'sort' => 'volume',
            'direction' => 'desc',
        ]);

        $controller = new StockListController();
        $response = $controller->index($request);
        $data = $response->getData();
        $orderedVolumes = $data['rows']->pluck('volume')->all();

        $this->assertSame([30, 20, 10], $orderedVolumes);

        Schema::dropIfExists($table);
    }

    public function test_selected_table_can_order_string_volume_column_descending_as_integer(): void
    {
        $table = 'stock_list_string_volume_sort_' . uniqid();

        Schema::dropIfExists($table);
        Schema::create($table, function ($tableBlueprint) {
            $tableBlueprint->id();
            $tableBlueprint->string('name');
            $tableBlueprint->string('volume');
            $tableBlueprint->timestamps();
        });

        DB::table($table)->insert([
            ['name' => 'Item A', 'volume' => '10,000'],
            ['name' => 'Item B', 'volume' => '30,000'],
            ['name' => 'Item C', 'volume' => '20,000'],
            ['name' => 'Item D', 'volume' => '50,000'],
            ['name' => 'Item E', 'volume' => '40,000'],
        ]);

        $request = Request::create('/stock-list', 'GET', [
            'table' => $table,
            'sort' => 'volume',
            'direction' => 'desc',
        ]);

        $controller = new StockListController();
        $response = $controller->index($request);
        $data = $response->getData();
        $orderedVolumes = $data['rows']->pluck('volume')->all();

        $this->assertSame(['50,000', '40,000', '30,000', '20,000', '10,000'], $orderedVolumes);

        Schema::dropIfExists($table);
    }

    public function test_watch_list_route_returns_top_5_for_requested_table(): void
    {
        $table = 'watch_list_top_' . uniqid();

        Schema::dropIfExists($table);
        Schema::create($table, function ($tableBlueprint) {
            $tableBlueprint->id();
            $tableBlueprint->string('stock_name');
            $tableBlueprint->string('symbol');
            $tableBlueprint->string('close');
            $tableBlueprint->string('change');
            $tableBlueprint->string('volume');
            $tableBlueprint->dateTime('created_at')->nullable();
        });

        DB::table($table)->insert([
            ['stock_name' => 'A', 'symbol' => 'A', 'close' => '1', 'change' => '1%', 'volume' => '10,000', 'created_at' => now()->toDateTimeString()],
            ['stock_name' => 'B', 'symbol' => 'B', 'close' => '2', 'change' => '2%', 'volume' => '20,000', 'created_at' => now()->toDateTimeString()],
            ['stock_name' => 'C', 'symbol' => 'C', 'close' => '3', 'change' => '3%', 'volume' => '30,000', 'created_at' => now()->toDateTimeString()],
            ['stock_name' => 'D', 'symbol' => 'D', 'close' => '4', 'change' => '4%', 'volume' => '40,000', 'created_at' => now()->toDateTimeString()],
            ['stock_name' => 'E', 'symbol' => 'E', 'close' => '5', 'change' => '5%', 'volume' => '50,000', 'created_at' => now()->toDateTimeString()],
            ['stock_name' => 'F', 'symbol' => 'F', 'close' => '6', 'change' => '6%', 'volume' => '83,96,583', 'created_at' => now()->toDateTimeString()],
        ]);

        $request = Request::create('/watch-list', 'GET', [
            'table' => $table,
        ]);

        $controller = new StockListController();
        $response = $controller->watchList($request);
        $data = $response->getData();

        $this->assertCount(5, $data['todayTop']);
        $this->assertEquals('F', $data['todayTop'][0]->symbol);

        Schema::dropIfExists($table);
    }

    public function test_selected_table_only_shows_requested_stock_columns(): void
    {
        $table = 'stock_list_requested_columns_' . uniqid();

        Schema::dropIfExists($table);
        Schema::create($table, function ($tableBlueprint) {
            $tableBlueprint->id();
            $tableBlueprint->string('stock_name');
            $tableBlueprint->string('symbol');
            $tableBlueprint->string('close');
            $tableBlueprint->string('change');
            $tableBlueprint->string('volume');
            $tableBlueprint->timestamps();
        });

        DB::table($table)->insert([
            [
                'stock_name' => 'Alpha',
                'symbol' => 'ALP',
                'close' => '10.50',
                'change' => '1.5%',
                'volume' => '10,000',
            ],
        ]);

        $request = Request::create('/stock-list', 'GET', [
            'table' => $table,
        ]);

        $controller = new StockListController();
        $response = $controller->index($request);
        $data = $response->getData();

        $this->assertSame(['stock_name', 'symbol', 'close', 'volume'], $data['columns']);

        Schema::dropIfExists($table);
    }

    public function test_stock_list_includes_each_symbol_occurrence_count(): void
    {
        $table = 'stock_list_symbol_count_' . uniqid();

        Schema::create($table, function ($tableBlueprint) {
            $tableBlueprint->id();
            $tableBlueprint->string('stock_name');
            $tableBlueprint->string('symbol');
            $tableBlueprint->integer('volume');
        });

        DB::table($table)->insert([
            ['stock_name' => 'Alpha', 'symbol' => 'ALP', 'volume' => 10],
            ['stock_name' => 'Alpha', 'symbol' => 'ALP', 'volume' => 20],
            ['stock_name' => 'Beta', 'symbol' => 'BET', 'volume' => 30],
        ]);

        $response = (new StockListController())->index(Request::create('/stock-list', 'GET', [
            'table' => $table,
        ]));

        $counts = $response->getData()['rows']->pluck('symbol_occurrence_count', 'symbol')->all();

        $this->assertSame(['ALP' => 2, 'BET' => 1], $counts);

        Schema::dropIfExists($table);
    }

    public function test_stock_list_marks_symbols_with_four_consecutive_days_and_high_volume(): void
    {
        $table = 'stock_list_buy_alert_' . uniqid();

        Schema::create($table, function ($tableBlueprint) {
            $tableBlueprint->id();
            $tableBlueprint->string('stock_name');
            $tableBlueprint->string('symbol');
            $tableBlueprint->string('volume');
            $tableBlueprint->dateTime('created_at');
        });

        $startDate = now()->startOfDay()->subDays(3);
        $rows = [];

        for ($day = 0; $day < 4; $day++) {
            $rows[] = [
                'stock_name' => 'Qualified Stock',
                'symbol' => 'QUAL',
                'volume' => $day === 3 ? '65,001' : '1,000',
                'created_at' => $startDate->copy()->addDays($day)->toDateTimeString(),
            ];
        }

        $rows[] = [
            'stock_name' => 'Low Volume Stock',
            'symbol' => 'LOW',
            'volume' => '65,000',
            'created_at' => now()->startOfDay()->toDateTimeString(),
        ];
        DB::table($table)->insert($rows);

        $response = (new StockListController())->index(Request::create('/stock-list', 'GET', [
            'table' => $table,
            'date' => now()->toDateString(),
        ]));

        $this->assertSame(['QUAL'], $response->getData()['buyAlertSymbols']);

        Schema::dropIfExists($table);
    }

    public function test_selected_table_shows_all_rows_without_pagination(): void
    {
        $table = 'stock_list_pagination_' . uniqid();

        Schema::dropIfExists($table);
        Schema::create($table, function ($tableBlueprint) {
            $tableBlueprint->id();
            $tableBlueprint->string('name');
            $tableBlueprint->timestamps();
        });

        for ($i = 1; $i <= 40; $i++) {
            DB::table($table)->insert([
                'name' => 'Item ' . $i,
            ]);
        }

        $request = Request::create('/stock-list', 'GET', [
            'table' => $table,
        ]);

        $controller = new StockListController();
        $response = $controller->index($request);
        $data = $response->getData();

        $this->assertInstanceOf(Collection::class, $data['rows']);
        $this->assertEquals(40, count($data['rows']));

        Schema::dropIfExists($table);
    }
}

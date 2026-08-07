<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StockSyncFeatureTest extends TestCase
{
    public function test_it_proxies_the_nse_stock_sync_request(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/api/v1/stocks*' => Http::response([
                'current_price' => 100.25,
                'ema_21' => 98.50,
                'ema_30_week' => 90.75,
            ]),
        ]);

        $response = $this->getJson(route('stock.list.sync', ['symbol' => 'RELIANCE']));

        $response->assertOk()
            ->assertJsonPath('current_price', 100.25)
            ->assertJsonPath('ema_21', 98.50)
            ->assertJsonPath('ema_30_week', 90.75);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:8001/api/v1/stocks?symbol=RELIANCE&exchange=NSE';
        });
    }
}

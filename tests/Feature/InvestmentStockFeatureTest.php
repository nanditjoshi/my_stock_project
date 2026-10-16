<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvestmentStockFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_displays_investment_stocks_grouped_from_the_service(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/api/v1/take_entry*' => Http::response([
                'total_matches' => 2,
                'near_9_ema' => [
                    [
                        'symbol' => 'GODREJAGRO',
                        'price' => '632.85',
                        'current_price' => '632.85',
                        'ema' => '632.32',
                        'ema_name' => '9 EMA',
                    ],
                ],
                'near_21_ema' => [
                    [
                        'symbol' => 'CAPILLARY',
                        'price' => '545.40',
                        'ema' => '545.34',
                    ],
                ],
            ]),
        ]);

        $response = $this->get(route('investment-stock.index'));

        $response->assertOk()
            ->assertSee('total_matches')
            ->assertSee('2', false)
            ->assertSee('near_9_ema')
            ->assertSee('GODREJAGRO')
            ->assertSee('Add')
            ->assertSee('near_21_ema')
            ->assertSee('CAPILLARY');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:8001/api/v1/take_entry?direction=above_ema&is_bull=true';
        });
    }

    public function test_it_shows_a_clear_error_when_the_service_is_unavailable(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/api/v1/take_entry*' => Http::response([], 503),
        ]);

        $this->get(route('investment-stock.index'))
            ->assertOk()
            ->assertSee('The investment stock service returned HTTP 503.');
    }

    public function test_it_adds_an_investment_stock_to_the_trading_journal(): void
    {
        $this->post(route('trading-journals.store-investment-stock'), [
            'symbol' => 'GODREJAGRO',
            'current_price' => '632.85',
            'ema_name' => '9 EMA',
        ])->assertRedirect(route('investment-stock.index'))
            ->assertSessionHas('success', 'GODREJAGRO added to the trading journal.');

        $this->assertDatabaseHas('trading_journals', [
            'symbol' => 'GODREJAGRO',
            'entry_date' => now()->toDateString(),
            'entry_price' => '632.8500',
            'reason_of_entry' => '9 EMA',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function index()
    {
        $symbols = $this->symbols();

        return view('report', compact('symbols'));
    }

    public function generate(Request $request): JsonResponse
    {
        $symbols = $this->symbols()->all();
        $validated = $request->validate([
            'symbol' => ['required', 'string', Rule::in($symbols)],
        ]);

        if (empty(config('services.openai.key'))) {
            return response()->json([
                'message' => 'OpenAI is not configured. Add OPENAI_API_KEY to the .env file and try again.',
            ], 503);
        }
        
        try {
            $response = (new Client(['timeout' => 90]))->post('https://api.openai.com/v1/responses', [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('services.openai.key'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => config('services.openai.model'),
                    'tools' => [['type' => 'web_search']],
                    'input' => $this->prompt($validated['symbol']),
                ],
                'http_errors' => false,
            ]);

            $payload = json_decode((string) $response->getBody(), true);

            if ($response->getStatusCode() >= 400) {
                Log::warning('OpenAI report generation failed.', ['status' => $response->getStatusCode()]);

                return response()->json([
                    'message' => $response->getStatusCode() === 429
                        ? 'OpenAI has rate-limited this request or the API account has no remaining quota. Please wait a moment or check the API account billing and limits.'
                        : 'The report could not be generated right now. Please try again shortly.',
                ], $response->getStatusCode() === 429 ? 429 : 502);
            }

            $report = $payload['output_text'] ?? $this->extractText($payload['output'] ?? []);

            if (!$report) {
                Log::warning('OpenAI returned an empty report response.');

                return response()->json([
                    'message' => 'The report service returned no analysis. Please try again.',
                ], 502);
            }

            return response()->json(['report' => $report]);
        } catch (\Throwable $exception) {
            Log::error('OpenAI report generation request failed.', ['exception' => $exception]);

            return response()->json([
                'message' => 'The report service is unavailable. Please try again shortly.',
            ], 503);
        }
    }

    protected function symbols()
    {
        return collect(['20_cross_50', '30w_ema_cross'])
            ->filter(function (string $table): bool {
                return Schema::hasTable($table) && Schema::hasColumn($table, 'symbol');
            })
            ->flatMap(function (string $table) {
                return DB::table($table)
                    ->whereNotNull('symbol')
                    ->pluck('symbol');
            })
            ->map(function ($symbol): string {
                return trim((string) $symbol);
            })
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    protected function prompt(string $symbol): string
    {
        return <<<PROMPT
Act like a professional stock market analyst. Give me a detailed scorecard analysis (out of 10) for the company {$symbol}.

Use web search to fact-check the analysis and prioritize the newest available company filings, exchange disclosures, earnings releases, and reputable financial reporting. Include recent developments and any credible corporate-governance concerns. Do not invent data. Clearly state when a material metric is unavailable or uncertain, and include a short Sources section with the URLs used.

Structure the output exactly as follows:
Overall Analyst Score (out of 10) – A single number reflecting long-term investment attractiveness.
Business Analysis (score + commentary) – Business model, product mix, market leadership, competitive advantages.
Fundamentals (score + commentary) – Revenue growth, profit margins, ROE/ROCE, debt levels, cash flows.
Valuation (score + commentary) – P/E, P/B, EV/EBITDA vs peers and historical averages.
Sector Analysis (score + commentary) – Industry growth, cyclicality, government policy impact, competition.
Moats & Risks (score + commentary) – Brand, distribution, innovation, along with risks like raw materials, regulation, disruption.
Forward Outlook (score + commentary) – Growth triggers, expansion plans, global opportunities.
Final Verdict (BUY / HOLD / AVOID) – With a clear rationale for long-term investors (5–10 years).

Keep the tone professional but accessible. This is educational information, not personalized investment advice.
PROMPT;
    }

    protected function extractText(array $output): string
    {
        return collect($output)
            ->flatMap(function (array $item) {
                return $item['content'] ?? [];
            })
            ->filter(function (array $content): bool {
                return ($content['type'] ?? null) === 'output_text';
            })
            ->pluck('text')
            ->filter()
            ->implode("\n");
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class InvestmentStockController extends Controller
{
    public function index()
    {
        $groups = [];
        $totalMatches = null;
        $error = null;

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get('http://127.0.0.1:8001/api/v1/take_entry', [
                    'direction' => 'above_ema',
                    'is_bull' => 'true',
                ]);
        } catch (ConnectionException $exception) {
            $error = 'Unable to connect to the investment stock service.';
        }

        if ($error === null && $response->failed()) {
            $error = 'The investment stock service returned HTTP '.$response->status().'.';
        } elseif ($error === null) {
            $data = $response->json();

            if (!is_array($data) || !isset($data['total_matches']) || !is_numeric($data['total_matches'])) {
                $error = 'The investment stock service returned an unexpected response.';
            } else {
                $totalMatches = (int) $data['total_matches'];

                foreach ($data as $name => $records) {
                    if ($name === 'total_matches') {
                        continue;
                    }

                    if (!is_array($records)) {
                        $error = 'The investment stock service returned an unexpected response.';
                        $groups = [];
                        $totalMatches = null;
                        break;
                    }

                    $columns = [];
                    foreach ($records as $record) {
                        if (!is_array($record)) {
                            $error = 'The investment stock service returned an unexpected response.';
                            $groups = [];
                            $totalMatches = null;
                            break 2;
                        }

                        $columns = array_values(array_unique(array_merge($columns, array_keys($record))));
                    }

                    $groups[] = [
                        'name' => $name,
                        'records' => $records,
                        'columns' => $columns,
                    ];
                }
            }
        }

        return view('investment-stock.index', compact('groups', 'totalMatches', 'error'));
    }
}

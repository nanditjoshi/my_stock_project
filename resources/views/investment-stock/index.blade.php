@extends('layouts.adminlte')

@section('title', 'Investment Stock')
@section('page_title', 'Investment Stock')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Stocks Above EMA</h3>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success" role="alert">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0 pl-3">
                        @foreach($errors->all() as $validationError)
                            <li>{{ $validationError }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if($error)
                <div class="alert alert-danger mb-0" role="alert">{{ $error }}</div>
            @elseif($totalMatches !== null)
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <tbody>
                            <tr>
                                <th scope="row" class="w-25">total_matches</th>
                                <td>{{ $totalMatches }}</td>
                            </tr>
                            @forelse($groups as $group)
                                <tr>
                                    <td colspan="2" class="p-0">
                                        <details class="investment-stock-group">
                                            <summary class="px-3 py-2">
                                                <strong>{{ $group['name'] }}</strong>
                                                <span class="badge badge-light border ml-1">{{ count($group['records']) }}</span>
                                            </summary>
                                            @if(count($group['records']))
                                                <div class="table-responsive px-3 pb-3">
                                                    <table class="table table-sm table-bordered table-striped mb-0">
                                                        <thead>
                                                            <tr>
                                                                @foreach($group['columns'] as $column)
                                                                    <th>{{ str_replace('_', ' ', $column) }}</th>
                                                                @endforeach
                                                                <th>Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($group['records'] as $record)
                                                                <tr>
                                                                    @foreach($group['columns'] as $column)
                                                                        @php($value = $record[$column] ?? '')
                                                                        <td>
                                                                            @if($column === 'symbol' && filled($value))
                                                                                <a href="https://www.tradingview.com/chart/?symbol=NSE:{{ rawurlencode((string) $value) }}" target="_blank" rel="noopener noreferrer">{{ $value }}</a>
                                                                            @else
                                                                                {{ is_scalar($value) ? $value : json_encode($value) }}
                                                                            @endif
                                                                        </td>
                                                                    @endforeach
                                                                    <td class="text-nowrap">
                                                                        @if(filled($record['symbol'] ?? null) && is_numeric($record['current_price'] ?? null) && filled($record['ema_name'] ?? null))
                                                                            <form method="POST" action="{{ route('trading-journals.store-investment-stock') }}">
                                                                                @csrf
                                                                                <input type="hidden" name="symbol" value="{{ $record['symbol'] }}">
                                                                                <input type="hidden" name="current_price" value="{{ $record['current_price'] }}">
                                                                                <input type="hidden" name="ema_name" value="{{ $record['ema_name'] }}">
                                                                                <button type="submit" class="btn btn-sm btn-primary">Add</button>
                                                                            </form>
                                                                        @else
                                                                            <button type="button" class="btn btn-sm btn-secondary" disabled title="Symbol, current price, or EMA name is missing">Add</button>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @else
                                                <div class="px-3 pb-3 text-muted">No stocks in this group.</div>
                                            @endif
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center text-muted">No stock groups were returned.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('styles')
<style>
    .investment-stock-group > summary {
        cursor: pointer;
        list-style-position: inside;
    }

    .investment-stock-group > summary:focus {
        outline: 2px solid #007bff;
        outline-offset: -2px;
    }
</style>
@endpush

@extends('layouts.adminlte')

@section('title', 'Watch List')
@section('page_title', 'Watch List')

@section('content')
    @php
        $displayColumns = collect($tableColumns)
            ->filter(fn ($column) => in_array($column, ['symbol', 'close', 'volume'], true))
            ->values()
            ->all();
    @endphp

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Top 5 Highest Volume Records</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('watch.list.index') }}" class="mb-4">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-6">
                        <label for="table">Select Table</label>
                        <select name="table" id="table" class="form-control" onchange="this.form.submit()">
                            <option value="">Choose a table</option>
                            @foreach($tables as $table)
                                <option value="{{ $table }}" {{ $selectedTable === $table ? 'selected' : '' }}>{{ $table }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>

            @if($selectedTable)
                <h5>Table: {{ $selectedTable }}</h5>
            @endif

            <div class="row">
                @foreach([
                    'One Day' => $todayTop,
                    'One Week' => $weekTop,
                    'Two Weeks' => $twoWeeksTop,
                    'One Month' => $monthTop,
                    'Quarterly' => $quarterTop,
                    'Half Yearly' => $halfYearTop,
                    'Yearly' => $yearTop,
                ] as $label => $rows)
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">{{ $label }}</h5>
                            </div>
                            <div class="card-body p-0">
                                @if(empty($rows))
                                    <div class="alert alert-info m-3 mb-0">No records found.</div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped mb-0">
                                            <thead>
                                                <tr>
                                                    @foreach($displayColumns as $column)
                                                        <th>{{ $column }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($rows as $row)
                                                    <tr>
                                                        @foreach($displayColumns as $column)
                                                            @php($value = $row->{$column} ?? '')
                                                            <td>
                                                                @if($column === 'symbol' && filled($value))
                                                                    <a href="https://www.tradingview.com/chart/?symbol=NSE:{{ rawurlencode((string) $value) }}" target="_blank" rel="noopener noreferrer">{{ $value }}</a>
                                                                @else
                                                                    {{ $value }}
                                                                @endif
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection

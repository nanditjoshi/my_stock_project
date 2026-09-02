@extends('layouts.adminlte')

@section('title', 'Watch List')
@section('page_title', 'Watch List')

@section('content')
    @php
        $priceColumn = in_array('close', $tableColumns, true) ? 'close' : 'price';
        $displayColumns = collect($tableColumns)
            ->filter(fn ($column) => in_array($column, ['symbol', $priceColumn, 'volume'], true))
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
                                                        <th>{{ $column === $priceColumn ? 'Latest Price' : $column }}</th>
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
                                                                    @if($tableSupportsWatched)
                                                                        <button type="button" class="btn btn-link btn-sm p-0 ml-1 watched-toggle" data-table="{{ $selectedTable }}" data-symbol="{{ $value }}" data-is-whatched="{{ $row->is_whatched ? '1' : '0' }}" aria-label="Toggle watched status"><i class="fas fa-star {{ $row->is_whatched ? 'text-success' : 'text-muted' }}"></i></button>
                                                                    @endif
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

@push('scripts')
<script>
    $(function () {
        $('.watched-toggle').on('click', async function () {
            var button = $(this);
            var watched = button.data('is-whatched') === 1 || button.data('is-whatched') === '1';
            button.prop('disabled', true);
            try {
                var response = await fetch('{{ route('stock.list.watched.toggle') }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ table: button.data('table'), symbol: button.data('symbol'), is_whatched: watched ? 0 : 1 })
                });
                var payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'Unable to update watched status.');
                $('.watched-toggle').filter(function () {
                    return $(this).data('table') === payload.table && $(this).data('symbol') === payload.symbol;
                }).each(function () {
                    $(this).data('is-whatched', payload.is_whatched ? 1 : 0).find('i')
                        .toggleClass('text-success', payload.is_whatched).toggleClass('text-muted', !payload.is_whatched);
                });
            } catch (error) {
                window.alert(error.message || 'Unable to update watched status.');
            } finally {
                button.prop('disabled', false);
            }
        });
    });
</script>
@endpush

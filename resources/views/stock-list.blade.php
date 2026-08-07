@extends('layouts.adminlte')

@section('title', 'Stock List')
@section('page_title', 'Stock List')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">View Stock Table</h3>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 pl-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="GET" action="{{ route('stock.list.index') }}">
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
                    <div class="form-group col-md-3">
                        <label for="date">Record Date</label>
                        <input type="date" name="date" id="date" class="form-control" value="{{ $date }}">
                    </div>
                    <div class="form-group col-md-3">
                        <button type="submit" name="action" value="apply" class="btn btn-primary btn-block">Apply Filter</button>
                    </div>
                </div>
            </form>

            @if($selectedTable)
                <div class="mt-4">
                    <h5>Table: {{ $selectedTable }}</h5>

                    @if($syncSummary)
                        <div class="alert alert-{{ $syncSummary['skipped'] ? 'warning' : 'success' }} mb-3">
                            Synced {{ $syncSummary['synced'] }} stock(s) to the watch list.
                            @if($syncSummary['skipped'])
                                {{ $syncSummary['skipped'] }} stock(s) were skipped because their indicators could not be retrieved.
                            @endif
                        </div>
                    @endif

                    @if(!$hasCreatedAt)
                        <div class="alert alert-warning mb-3">This table has no <code>created_at</code> column, so date filtering is unavailable.</div>
                    @endif

                    @if($querySql)
                        <div class="alert alert-secondary small mb-3">
                            <strong>Query:</strong>
                            <div class="mt-1"><code>{{ $querySql }}</code></div>
                        </div>
                    @endif

                    @if($rows->isEmpty())
                        <div class="alert alert-info mb-0">No records found in this table.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        @foreach($columns as $column)
                                            @if($column === 'volume')
                                                <th>
                                                    <a href="{{ route('stock.list.index', ['table' => $selectedTable, 'date' => $date, 'sort' => 'volume', 'direction' => $direction === 'asc' ? 'desc' : 'asc']) }}">
                                                        {{ $column }}
                                                        @if($sort === 'volume')
                                                            <span class="ml-1">{{ $direction === 'asc' ? '↑' : '↓' }}</span>
                                                        @endif
                                                    </a>
                                                </th>
                                            @else
                                                <th>{{ $column }}</th>
                                            @endif
                                        @endforeach
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $row)
                                        <tr>
                                            @foreach($columns as $column)
                                                @php($value = $row->{$column} ?? '')
                                                <td>
                                                    @if($column === 'symbol' && filled($value))
                                                        <a href="https://www.tradingview.com/chart/?symbol=NSE:{{ rawurlencode((string) $value) }}" target="_blank" rel="noopener noreferrer">{{ $value }}</a>
                                                    @else
                                                        {{ $value }}
                                                    @endif
                                                </td>
                                            @endforeach
                                            @php($symbol = $row->symbol ?? '')
                                            @php($price = $row->price ?? $row->close ?? '')
                                            <td>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-primary add-to-watch-list"
                                                    data-toggle="modal"
                                                    data-target="#add-watch-list-modal"
                                                    data-symbol="{{ $symbol }}"
                                                    data-price="{{ str_replace(',', '', (string) $price) }}"
                                                >
                                                    Add
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="text-muted mt-3">
                            Showing all {{ $rows->count() }} records
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="add-watch-list-modal" tabindex="-1" role="dialog" aria-labelledby="add-watch-list-title" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('stock.list.watch-list.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="add-watch-list-title">Add to Watch List</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="watch-list-symbol">Symbol</label>
                        <div class="input-group">
                            <input type="text" name="symbol" id="watch-list-symbol" class="form-control" required>
                            <div class="input-group-append">
                                <button type="button" id="sync-stock-data" class="btn btn-outline-primary">Sync</button>
                            </div>
                        </div>
                        <small id="sync-stock-status" class="form-text text-muted"></small>
                    </div>
                    <div class="form-group">
                        <label for="watch-list-price">Price</label>
                        <input type="number" name="price" id="watch-list-price" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="watch-list-current-price">Current Price</label>
                        <input type="number" name="current_price" id="watch-list-current-price" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="watch-list-9ema">9 EMA</label>
                        <input type="number" name="9ema" id="watch-list-9ema" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="watch-list-21ema">21 EMA</label>
                        <input type="number" name="21ema" id="watch-list-21ema" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group mb-0">
                        <label for="watch-list-30wema">30 Week EMA</label>
                        <input type="number" name="30wema" id="watch-list-30wema" class="form-control" step="0.01" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add to Watch List</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('.add-to-watch-list').on('click', function () {
            $('#watch-list-symbol').val($(this).data('symbol'));
            $('#watch-list-price').val($(this).data('price'));
            $('#watch-list-current-price, #watch-list-9ema, #watch-list-21ema, #watch-list-30wema').val('');
            $('#sync-stock-status').text('').removeClass('text-danger text-success');
        });

        $('#sync-stock-data').on('click', async function () {
            var symbol = $.trim($('#watch-list-symbol').val());
            var button = $(this);
            var status = $('#sync-stock-status');

            if (!symbol) {
                status.text('Enter a symbol before syncing.').removeClass('text-success').addClass('text-danger');
                return;
            }

            button.prop('disabled', true).text('Syncing...');
            status.text('Fetching latest NSE indicators...').removeClass('text-danger text-success');

            try {
                var response = await fetch('{{ route('stock.list.sync') }}?symbol=' + encodeURIComponent(symbol), {
                    headers: { 'Accept': 'application/json' }
                });
                var payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.message || 'Sync failed.');
                }

                // The service may return the values at the root or inside data/result.
                var data = payload.data || payload.result || payload;
                data = Array.isArray(data) ? data[0] : data;
                var currentPrice = data.current_price ?? data.currentPrice ?? data.price ?? data.close;
                var ema9 = data['9ema'] ?? data.ema_9 ?? data.ema9 ?? data['9_ema'] ?? data.EMA9;
                var ema21 = data['21ema'] ?? data.ema_21 ?? data.ema21 ?? data['21_ema'] ?? data.EMA21;
                var ema30Week = data['30wema'] ?? data.ema_30_week ?? data.ema30week ?? data['30_week_ema'] ?? data.ema_30w ?? data.EMA30W;

                if (currentPrice === undefined || ema9 === undefined || ema21 === undefined || ema30Week === undefined) {
                    throw new Error('The sync service response is missing one or more required indicators.');
                }

                $('#watch-list-current-price').val(currentPrice);
                $('#watch-list-9ema').val(ema9);
                $('#watch-list-21ema').val(ema21);
                $('#watch-list-30wema').val(ema30Week);
                status.text('Latest values synced.').removeClass('text-danger').addClass('text-success');
            } catch (error) {
                status.text(error.message || 'Unable to sync stock data.').removeClass('text-success').addClass('text-danger');
            } finally {
                button.prop('disabled', false).text('Sync');
            }
        });
    });
</script>
@endpush

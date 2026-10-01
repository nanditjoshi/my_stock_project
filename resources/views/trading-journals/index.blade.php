@extends('layouts.adminlte')

@section('title', 'Entry Of Stock')
@section('page_title', 'Entry Of Stock')

@push('styles')
<style>
    .symbol-search-results { position: absolute; z-index: 1050; top: 100%; left: 0; right: 0; max-height: 240px; overflow-y: auto; }
    .symbol-search-result { cursor: pointer; }
</style>
@endpush

@section('content')
    <div class="card">
        <div class="card-header"><h3 class="card-title">New Trading Journal Entry</h3></div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 pl-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <form method="POST" action="{{ route('trading-journals.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="symbol">Symbol</label>
                        <div class="position-relative">
                            <input id="symbol" name="symbol" class="form-control" maxlength="32" value="{{ old('symbol') }}" autocomplete="off" aria-autocomplete="list" aria-controls="symbol-search-results" required>
                            <div id="symbol-search-results" class="list-group symbol-search-results d-none" role="listbox"></div>
                        </div>
                        <small id="symbol-search-status" class="form-text text-muted">Type at least 2 characters to search stock snapshots.</small>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="entry_date">Entry Date</label>
                        <input type="date" id="entry_date" name="entry_date" class="form-control" value="{{ old('entry_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="exit_date">Exit Date</label>
                        <input type="date" id="exit_date" name="exit_date" class="form-control" value="{{ old('exit_date') }}">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="reason_of_entry">Reason of Entry</label>
                        <textarea id="reason_of_entry" name="reason_of_entry" class="form-control" rows="2">{{ old('reason_of_entry') }}</textarea>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="reason_of_exit">Reason of Exit</label>
                        <textarea id="reason_of_exit" name="reason_of_exit" class="form-control" rows="2">{{ old('reason_of_exit') }}</textarea>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="setup">Setup</label>
                        <input id="setup" name="setup" class="form-control" maxlength="255" value="{{ old('setup') }}">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="entry_price">Entry Price</label>
                        <input type="number" step="0.0001" min="0" id="entry_price" name="entry_price" class="form-control" value="{{ old('entry_price') }}" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="exit_price">Exit Price</label>
                        <input type="number" step="0.0001" min="0" id="exit_price" name="exit_price" class="form-control" value="{{ old('exit_price') }}">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="quantity">Quantity</label>
                        <input type="number" step="1" min="0" id="quantity" name="quantity" class="form-control" value="{{ old('quantity', 0) }}" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="current_price">Current Price</label>
                        <input type="number" step="0.0001" min="0" id="current_price" name="current_price" class="form-control" value="{{ old('current_price') }}">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="buying_average">Buying Average</label>
                        <input type="number" step="0.0001" min="0" id="buying_average" name="buying_average" class="form-control" value="{{ old('buying_average') }}">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="profit_loss">Profit / Loss</label>
                        <input type="number" step="0.01" id="profit_loss" class="form-control" value="{{ old('profit_loss') }}" readonly>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="profit_loss_percentage">Profit / Loss Percentage</label>
                        <input type="number" step="0.0001" id="profit_loss_percentage" class="form-control" value="{{ old('profit_loss_percentage') }}" readonly>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="days_held">Number of Days Held</label>
                        <input type="number" min="0" id="days_held" class="form-control" value="{{ old('days_held') }}" readonly>
                        <small class="form-text text-muted">Calculated from entry date to exit date.</small>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="entry_image">Entry Image</label>
                        <input type="file" accept="image/*" id="entry_image" name="entry_image" class="form-control-file">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="exit_image">Exit Image</label>
                        <input type="file" accept="image/*" id="exit_image" name="exit_image" class="form-control-file">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save Journal Entry</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Trading Journal Records</h3></div>
        <div class="card-body">
            @if($journals->isEmpty())
                <p class="text-muted mb-0">No trading journal records yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm">
                        <thead>
                            <tr>
                                
                                <th>Symbol</th>
                                <th>Entry Date</th>
                                <th>Exit Date</th>
                                
                                
                                <th>Entry Price</th>
                                <th>Exit Price</th>
                                <th>Current Price</th>
                                <th>Buying Average</th>
                                <th>Quantity</th>
                                <th>Total Investment</th>
                                <th>Profit / Loss</th>
                                <th>P/L %</th>
                                <th>Days Hold</th>
                                <th>Entry Image</th>
                                <th>Exit Image</th>
                                
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($journals as $journal)
                                <tr>
                                
                                    <td><strong>{{ $journal->symbol }}</strong></td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($journal->entry_date)->format('d/m/Y') }}</td>
                                    <td>{{ $journal->exit_date ? \Illuminate\Support\Carbon::parse($journal->exit_date)->format('d/m/Y') : '—' }}</td>
                                
                                    <td>{{ $journal->entry_price }}</td>
                                    <td>{{ $journal->exit_price ?? '—' }}</td>
                                    <td>{{ $journal->current_price ?? '—' }}</td>
                                    <td>{{ $journal->buying_average ?? '—' }}</td>
                                    <td>{{ $journal->quantity }}</td>
                                    <td>{{ $journal->buying_average !== null ? number_format((float) $journal->buying_average * (int) $journal->quantity, 2) : '—' }}</td>
                                    <td class="{{ $journal->profit_loss > 0 ? 'text-success font-weight-bold' : ($journal->profit_loss < 0 ? 'text-danger font-weight-bold' : '') }}">{{ $journal->profit_loss ?? '—' }}</td>
                                    <td class="{{ $journal->profit_loss_percentage > 0 ? 'text-success font-weight-bold' : ($journal->profit_loss_percentage < 0 ? 'text-danger font-weight-bold' : '') }}">{{ $journal->profit_loss_percentage !== null ? $journal->profit_loss_percentage . '%' : '—' }}</td>
                                    <td>{{ $journal->days_held ?? '—' }}</td>
                                    <td>@if($journal->entry_image)<a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($journal->entry_image) }}" target="_blank" rel="noopener">View</a>@else—@endif</td>
                                    <td>@if($journal->exit_image)<a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($journal->exit_image) }}" target="_blank" rel="noopener">View</a>@else—@endif</td>
                                    
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $journals->links() }}
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var currentPrice = document.getElementById('current_price');
        var buyingAverage = document.getElementById('buying_average');
        var quantity = document.getElementById('quantity');
        var profitLoss = document.getElementById('profit_loss');
        var profitLossPercentage = document.getElementById('profit_loss_percentage');
        var entryDate = document.getElementById('entry_date');
        var exitDate = document.getElementById('exit_date');
        var daysHeld = document.getElementById('days_held');

        function updateCalculations() {
            var current = parseFloat(currentPrice.value);
            var average = parseFloat(buyingAverage.value);
            if (Number.isFinite(current) && Number.isFinite(average) && average > 0) {
                var pnl = current - average;
                profitLoss.value = (pnl * (parseInt(quantity.value, 10) || 0)).toFixed(2);
                profitLossPercentage.value = ((pnl / average) * 100).toFixed(4);
            } else {
                profitLoss.value = '';
                profitLossPercentage.value = '';
            }

            if (entryDate.value && exitDate.value) {
                var start = new Date(entryDate.value + 'T00:00:00Z');
                var end = new Date(exitDate.value + 'T00:00:00Z');
                daysHeld.value = Math.max(0, Math.round((end - start) / 86400000));
            } else {
                daysHeld.value = '';
            }
        }

        [currentPrice, buyingAverage, quantity, entryDate, exitDate].forEach(function (field) {
            field.addEventListener('input', updateCalculations);
            field.addEventListener('change', updateCalculations);
        });
        updateCalculations();
    })();

    (function () {
        var input = document.getElementById('symbol');
        var results = document.getElementById('symbol-search-results');
        var status = document.getElementById('symbol-search-status');
        var timer;
        var activeRequest;

        function hideResults() {
            results.classList.add('d-none');
            results.innerHTML = '';
        }

        input.addEventListener('input', function () {
            var query = input.value.trim();
            window.clearTimeout(timer);
            if (activeRequest) activeRequest.abort();
            hideResults();

            if (query.length < 2) {
                status.textContent = 'Type at least 2 characters to search stock snapshots.';
                return;
            }

            status.textContent = 'Searching symbols…';
            timer = window.setTimeout(function () {
                activeRequest = new AbortController();
                fetch('{{ route('trading-journals.symbol-search') }}?q=' + encodeURIComponent(query), {
                    headers: { 'Accept': 'application/json' },
                    signal: activeRequest.signal
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Symbol search failed.');
                        return response.json();
                    })
                    .then(function (data) {
                        if (input.value.trim() !== query) return;
                        results.innerHTML = '';
                        (data.symbols || []).forEach(function (symbol) {
                            var option = document.createElement('button');
                            option.type = 'button';
                            option.className = 'list-group-item list-group-item-action symbol-search-result';
                            option.setAttribute('role', 'option');
                            option.textContent = symbol;
                            option.addEventListener('click', function () {
                                input.value = symbol;
                                hideResults();
                                status.textContent = 'Symbol selected.';
                            });
                            results.appendChild(option);
                        });
                        results.classList.toggle('d-none', !data.symbols || data.symbols.length === 0);
                        status.textContent = data.symbols && data.symbols.length
                            ? data.symbols.length + ' matching symbol(s).'
                            : 'No matching symbols found.';
                    })
                    .catch(function (error) {
                        if (error.name !== 'AbortError') status.textContent = 'Could not search symbols. Try again.';
                    });
            }, 250);
        });

        document.addEventListener('click', function (event) {
            if (!results.contains(event.target) && event.target !== input) hideResults();
        });
    })();
</script>
@endpush


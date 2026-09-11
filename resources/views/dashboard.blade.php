@extends('layouts.adminlte')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@push('styles')
<style>
    .sm-data-table .sm-symbol-column,
    .sm-data-table .sm-price-column {
        background-color: #fff;
        position: sticky;
        z-index: 2;
    }

    .sm-data-table .sm-symbol-column { left: 0; min-width: 150px; }
    .sm-data-table .sm-price-column { left: 150px; min-width: 90px; }
    .sm-data-table thead .sm-symbol-column,
    .sm-data-table thead .sm-price-column { z-index: 3; }
    .sm-data-table .sm-date-column { padding: .3rem .45rem; white-space: nowrap; width: 1%; }

    .stock-chart-preview {
        display: none;
        position: fixed;
        width: 440px;
        max-width: calc(100vw - 24px);
        height: 330px;
        max-height: calc(100vh - 24px);
        z-index: 1080;
        background: #fff;
        border: 1px solid rgba(0, 0, 0, .2);
        border-radius: .25rem;
        box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .2);
        overflow: hidden;
    }

    .stock-chart-preview.is-visible { display: flex; flex-direction: column; }
    .stock-chart-preview__header { padding: .45rem .65rem; border-bottom: 1px solid #dee2e6; }
    .stock-chart-preview__chart { flex: 1; min-height: 0; border: 0; width: 100%; }

    @media (hover: none), (pointer: coarse) {
        .stock-chart-preview { display: none !important; }
    }
</style>
@endpush

@section('content')
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>0</h3>
                    <p>Users</p>
                </div>
                <div class="icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>0</h3>
                    <p>Tables</p>
                </div>
                <div class="icon"><i class="fas fa-table"></i></div>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <form method="POST" action="{{ route('dashboard.stock-snapshots.sync') }}">
            @csrf
            <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt mr-1"></i> Sync Stock Snapshots</button>
        </form>
    </div>

    @if(session('snapshotSyncSummary'))
        @php($snapshotSyncSummary = session('snapshotSyncSummary'))
        <div class="alert alert-{{ $snapshotSyncSummary['skipped'] ? 'warning' : 'success' }}">
            Synced {{ $snapshotSyncSummary['synced'] }} stock snapshot(s).
            @if($snapshotSyncSummary['skipped'])
                {{ $snapshotSyncSummary['skipped'] }} snapshot(s) could not be refreshed.
            @endif
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">SM Data — 30w EMA Cross <span class="text-muted">({{ $smData['recordCount'] }} records)</span></h3>
            <div class="card-tools">
                <a href="{{ route('dashboard.download-sm-data') }}" class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i> Download SM Data</a>
            </div>
        </div>
        <div class="card-body p-0">
            @if($smData['rows']->isEmpty())
                <div class="alert alert-info m-3 mb-0">No 30w EMA cross records found.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0 sm-data-table">
                        <thead>
                            <tr>
                                <th class="sm-symbol-column">Symbol</th>
                                <th class="sm-price-column">Close / Price</th>
                                @foreach($smData['dates'] as $date)
                                    <th class="sm-date-column">{{ \Illuminate\Support\Carbon::parse($date)->format('dm') }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($smData['rows'] as $row)
                                <tr>
                                    <td class="sm-symbol-column">
                                        <a class="stock-symbol-preview" href="https://www.tradingview.com/chart/?symbol=NSE:{{ rawurlencode((string) $row->symbol) }}" data-symbol="{{ $row->symbol }}" target="_blank" rel="noopener noreferrer">{{ $row->symbol }}</a>
                                        <button type="button" class="btn btn-link btn-sm p-0 ml-1 watched-toggle" data-table="30w_ema_cross" data-symbol="{{ $row->symbol }}" data-is-whatched="{{ $row->is_whatched ? '1' : '0' }}" aria-label="Toggle watched status"><i class="fas fa-star {{ $row->is_whatched ? 'text-success' : 'text-muted' }}"></i></button>
                                    </td>
                                    <td class="sm-price-column">{{ $row->price }}</td>
                                    @foreach($smData['dates'] as $date)
                                        @php($change = $row->changes[$date] ?? null)
                                        @php($numericChange = (float) str_replace([',', '%'], '', (string) $change))
                                        <td class="sm-date-column {{ $change !== null && $numericChange > 0 ? 'text-success font-weight-bold' : ($change !== null && $numericChange < 0 ? 'text-danger font-weight-bold' : '') }}">{{ $change }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Watched Stocks</h3></div>
        <div class="card-body p-0">
            @if($watchedRecords->isEmpty())
                <div class="alert alert-info m-3 mb-0">No watched stocks yet.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead><tr><th>Symbol</th><th>Price</th><th>is_whatched</th></tr></thead>
                        <tbody>
                            @foreach($watchedRecords as $record)
                                <tr data-watched-row="{{ $record->table }}-{{ $record->symbol }}">
                                    <td>
                                        <a class="stock-symbol-preview" href="https://www.tradingview.com/chart/?symbol=NSE:{{ rawurlencode((string) $record->symbol) }}" data-symbol="{{ $record->symbol }}" target="_blank" rel="noopener noreferrer">{{ $record->symbol }}</a>
                                    </td>
                                    <td>{{ $record->price }}</td>
                                    <td><button type="button" class="btn btn-link btn-sm p-0 watched-toggle" data-table="{{ $record->table }}" data-symbol="{{ $record->symbol }}" data-is-whatched="1" aria-label="Toggle watched status"><i class="fas fa-star text-success"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        var preview = $('<div>', {
            class: 'stock-chart-preview',
            role: 'dialog',
            'aria-label': 'Stock chart preview'
        }).appendTo('body');
        var closeTimer;
        var activeSymbol;

        function chartUrl(symbol) {
            var params = new URLSearchParams({
                symbol: 'NSE:' + symbol,
                interval: 'D',
                hidesidetoolbar: '1',
                symboledit: '1',
                saveimage: '0',
                toolbarbg: 'f1f3f6',
                hideideas: '1',
                theme: 'light',
                style: '1',
                timezone: 'Asia/Kolkata',
                withdateranges: '1',
                locale: 'en'
            });

            return 'https://s.tradingview.com/widgetembed/?' + params.toString();
        }

        function positionPreview(link) {
            var rect = link.getBoundingClientRect();
            var width = preview.outerWidth();
            var height = preview.outerHeight();
            var left = rect.right + 12;
            var top = rect.top;

            if (left + width > window.innerWidth - 12) left = rect.left - width - 12;
            if (left < 12) left = Math.max(12, window.innerWidth - width - 12);
            if (top + height > window.innerHeight - 12) top = Math.max(12, window.innerHeight - height - 12);

            preview.css({ left: left + 'px', top: top + 'px' });
        }

        function openPreview(link) {
        return false;
            window.clearTimeout(closeTimer);
            var symbol = String($(link).data('symbol') || '').trim();
            if (!symbol) return;

            if (activeSymbol !== symbol) {
                activeSymbol = symbol;
                preview.empty().append(
                    $('<div>', { class: 'stock-chart-preview__header d-flex justify-content-between align-items-center' }).append(
                        $('<strong>').text(symbol + ' — Daily chart'),
                        $('<a>', { href: link.href, target: '_blank', rel: 'noopener noreferrer', class: 'btn btn-outline-primary btn-xs ml-2' }).text('Open full chart')
                    ),
                    $('<iframe>', { class: 'stock-chart-preview__chart', src: chartUrl(symbol), title: symbol + ' chart', loading: 'lazy' })
                );
            }

            preview.addClass('is-visible');
            positionPreview(link);
        }

        function scheduleClose() {
            closeTimer = window.setTimeout(function () {
                preview.removeClass('is-visible');
                activeSymbol = null;
            }, 180);
        }

        $('.stock-symbol-preview')
            .on('mouseenter focusin', function () { openPreview(this); })
            .on('mouseleave focusout', scheduleClose);

        preview
            .on('mouseenter focusin', function () { window.clearTimeout(closeTimer); })
            .on('mouseleave focusout', scheduleClose);

        $(window).on('scroll resize', function () {
            preview.removeClass('is-visible');
            activeSymbol = null;
        });

        $(document).on('keydown', function (event) {
            if (event.key === 'Escape') {
                preview.removeClass('is-visible');
                activeSymbol = null;
            }
        });

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

                if (!payload.is_whatched) {
                    $('[data-watched-row="' + payload.table + '-' + payload.symbol + '"]').remove();
                }
            } catch (error) {
                window.alert(error.message || 'Unable to update watched status.');
                button.prop('disabled', false);
            }
        });
    });
</script>
@endpush

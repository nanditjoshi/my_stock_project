@extends('layouts.adminlte')

@section('title', 'Report')
@section('page_title', 'Report')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Symbol Report</h3>
        </div>
        <div class="card-body">
            <form id="report-form" data-generate-url="{{ route('report.generate') }}">
                <div class="form-group mb-0 col-md-6 px-0">
                    <label for="symbol">Select Symbol</label>
                    <select name="symbol" id="symbol" class="form-control">
                        <option value="">Choose a symbol</option>
                        @foreach($symbols as $symbol)
                            <option value="{{ $symbol }}">{{ $symbol }}</option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">Selecting a symbol generates a new, fact-checked report.</small>
                </div>
            </form>

            @if($symbols->isEmpty())
                <div class="alert alert-info mt-3 mb-0">
                    No symbols were found in the 20_cross_50 or 30w_ema_cross tables.
                </div>
            @endif

            <div id="report-status" class="mt-3" aria-live="polite"></div>
            <div id="report-result" class="card card-outline card-primary mt-3 d-none">
                <div class="card-header"><h3 class="card-title">Generated Analysis</h3></div>
                <div class="card-body" id="report-content" style="white-space: pre-wrap;"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        var $symbol = $('#symbol');
        var $status = $('#report-status');
        var $result = $('#report-result');
        var $content = $('#report-content');

        $symbol.on('change', function () {
            var symbol = $symbol.val();
            $result.addClass('d-none');
            $content.empty();

            if (!symbol) {
                $status.empty();
                return;
            }

            $symbol.prop('disabled', true);
            $status.html('<div class="alert alert-info mb-0"><i class="fas fa-spinner fa-spin mr-2"></i>Generating a fact-checked report for ' + $('<div>').text(symbol).html() + '…</div>');

            $.ajax({
                url: $('#report-form').data('generate-url'),
                method: 'POST',
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                data: { symbol: symbol }
            }).done(function (response) {
                $status.empty();
                $content.text(response.report);
                $result.removeClass('d-none');
            }).fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'The report could not be generated. Please try again.';
                $status.html('<div class="alert alert-danger mb-0"></div>').find('.alert').text(message);
            }).always(function () {
                $symbol.prop('disabled', false);
            });
        });
    });
</script>
@endpush

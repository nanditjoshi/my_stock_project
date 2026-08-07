@if($selectedTable)
    <div class="mt-4">
        <h5>Table: {{ $selectedTable }}</h5>

        @if($rows->isEmpty())
            <div class="alert alert-info mb-0">No records found in this table.</div>
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            @foreach($columns as $column)
                                <th>{{ $column }}</th>
                            @endforeach
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

@extends('layouts.adminlte')

@section('title', 'CSV Import')
@section('page_title', 'CSV Import')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Import CSV</h3>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('csv.import.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <label for="mode">Import Mode</label>
                    <select name="mode" id="mode" class="form-control">
                        <option value="existing">Use Existing Table</option>
                        <option value="new">Create New Table From CSV</option>
                    </select>
                </div>

                <div class="form-group existing-table-group">
                    <label for="selected_table">Select Existing Table</label>
                    <select name="selected_table" id="selected_table" class="form-control">
                        <option value="">Select table</option>
                        @foreach($tables as $table)
                            <option value="{{ $table }}">{{ $table }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group new-table-group" style="display:none;">
                    <label for="new_table_name">New Table Name</label>
                    <input type="text" name="new_table_name" id="new_table_name" class="form-control" placeholder="Example: stock_data">
                </div>

                <div class="form-group">
                    <label for="file">Upload CSV</label>
                    <input type="file" name="file" id="file" class="form-control-file" accept=".csv,text/csv">
                </div>

                <button type="submit" class="btn btn-primary">Import</button>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('mode').addEventListener('change', function () {
                const mode = this.value;
                document.querySelector('.existing-table-group').style.display = mode === 'existing' ? 'block' : 'none';
                document.querySelector('.new-table-group').style.display = mode === 'new' ? 'block' : 'none';
            });
        </script>
    @endpush
@endsection

@extends('layouts.adminlte')
@section('title', 'Portfolio')
@section('page_title', 'Portfolio')
@section('content')<div class="card"><div class="card-header"><a href="{{ route('portfolio.create') }}" class="btn btn-primary btn-sm">Add Portfolio Entry</a></div><div class="card-body">
    @include('shared.flash')<div class="table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>User / Account</th><th>Type</th><th>Symbol</th><th>Qty</th><th>Investment Price</th><th>Current Price</th><th>Total Investment</th><th>Total P/L</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    @forelse($portfolios as $portfolio)<tr><td>{{ $portfolio->account->user->name }} / {{ $portfolio->account->acc_name }}</td><td>{{ $portfolio->investmentType->name }}</td><td>{{ $portfolio->symbol ?: '—' }}</td><td>{{ $portfolio->qty }}</td><td>{{ $portfolio->investement_price }}</td><td>{{ $portfolio->current_price }}</td><td>{{ $portfolio->total_investment }}</td><td>{{ $portfolio->total_PL }}</td><td>{{ $portfolio->status == '1' ? 'Active' : 'Inactive' }}</td><td><a class="btn btn-warning btn-sm" href="{{ route('portfolio.edit', $portfolio) }}">Edit</a> <form class="d-inline" method="POST" action="{{ route('portfolio.destroy', $portfolio) }}" onsubmit="return confirm('Delete this portfolio entry?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Delete</button></form></td></tr>
    @empty<tr><td colspan="10" class="text-center">No portfolio entries found.</td></tr>@endforelse</tbody></table></div>{{ $portfolios->links() }}
</div></div>@endsection

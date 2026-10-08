@extends('layouts.adminlte')
@section('title', 'Investment Types')
@section('page_title', 'Investment Types')
@section('content')<div class="card"><div class="card-header"><a href="{{ route('investment-types.create') }}" class="btn btn-primary btn-sm">Add Investment Type</a></div><div class="card-body">
    @include('shared.flash')<div class="table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>Name</th><th>Status</th><th>Portfolio Entries</th><th>Actions</th></tr></thead><tbody>
    @forelse($investmentTypes as $investmentType)<tr><td>{{ $investmentType->name }}</td><td>{{ $investmentType->status == '1' ? 'Active' : 'Inactive' }}</td><td>{{ $investmentType->portfolios_count }}</td><td><a class="btn btn-warning btn-sm" href="{{ route('investment-types.edit', $investmentType) }}">Edit</a> <form class="d-inline" method="POST" action="{{ route('investment-types.destroy', $investmentType) }}" onsubmit="return confirm('Delete this investment type?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Delete</button></form></td></tr>
    @empty<tr><td colspan="4" class="text-center">No investment types found.</td></tr>@endforelse</tbody></table></div>{{ $investmentTypes->links() }}
</div></div>@endsection

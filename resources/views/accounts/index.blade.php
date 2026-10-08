@extends('layouts.adminlte')
@section('title', 'Accounts')
@section('page_title', 'Accounts')
@section('content')
<div class="card">
    <div class="card-header"><a href="{{ route('accounts.create') }}" class="btn btn-primary btn-sm">Add Account</a></div>
    <div class="card-body">
        @include('shared.flash')
        <div class="table-responsive"><table class="table table-bordered table-striped">
            <thead><tr><th>User</th><th>Account Name</th><th>Account Number</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>@forelse($accounts as $account)
                <tr><td>{{ $account->user->name }}</td><td>{{ $account->acc_name }}</td><td>{{ $account->acc_number ?: '—' }}</td><td>{{ $account->status == '1' ? 'Active' : 'Inactive' }}</td>
                    <td><a class="btn btn-warning btn-sm" href="{{ route('accounts.edit', $account) }}">Edit</a>
                        <form class="d-inline" method="POST" action="{{ route('accounts.destroy', $account) }}" onsubmit="return confirm('Delete this account and all its portfolio entries?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Delete</button></form></td></tr>
            @empty<tr><td colspan="5" class="text-center">No accounts found.</td></tr>@endforelse</tbody>
        </table></div>{{ $accounts->links() }}
    </div>
</div>
@endsection

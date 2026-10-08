@extends('layouts.adminlte')
@section('title', 'Edit Account')
@section('page_title', 'Edit Account')
@section('content')<div class="card"><div class="card-body">@include('shared.flash')<form method="POST" action="{{ route('accounts.update', $account) }}">@csrf @method('PUT') @include('accounts._form')</form></div></div>@endsection

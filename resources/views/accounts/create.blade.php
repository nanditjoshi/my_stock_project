@extends('layouts.adminlte')
@section('title', 'Add Account')
@section('page_title', 'Add Account')
@section('content')<div class="card"><div class="card-body">@include('shared.flash')<form method="POST" action="{{ route('accounts.store') }}">@csrf @include('accounts._form')</form></div></div>@endsection

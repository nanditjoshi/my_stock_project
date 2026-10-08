@extends('layouts.adminlte')
@section('title', 'Add Investment Type')
@section('page_title', 'Add Investment Type')
@section('content')<div class="card"><div class="card-body">@include('shared.flash')<form method="POST" action="{{ route('investment-types.store') }}">@csrf @include('investment-types._form')</form></div></div>@endsection

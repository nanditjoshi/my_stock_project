@extends('layouts.adminlte')
@section('title', 'Add Portfolio Entry')
@section('page_title', 'Add Portfolio Entry')
@section('content')<div class="card"><div class="card-body">@include('shared.flash')<form method="POST" action="{{ route('portfolio.store') }}">@csrf @include('portfolio._form')</form></div></div>@endsection

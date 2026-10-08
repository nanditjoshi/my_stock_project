@extends('layouts.adminlte')
@section('title', 'Edit Portfolio Entry')
@section('page_title', 'Edit Portfolio Entry')
@section('content')<div class="card"><div class="card-body">@include('shared.flash')<form method="POST" action="{{ route('portfolio.update', $portfolio) }}">@csrf @method('PUT') @include('portfolio._form')</form></div></div>@endsection

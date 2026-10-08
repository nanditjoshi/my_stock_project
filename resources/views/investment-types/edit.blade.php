@extends('layouts.adminlte')
@section('title', 'Edit Investment Type')
@section('page_title', 'Edit Investment Type')
@section('content')<div class="card"><div class="card-body">@include('shared.flash')<form method="POST" action="{{ route('investment-types.update', $investmentType) }}">@csrf @method('PUT') @include('investment-types._form')</form></div></div>@endsection

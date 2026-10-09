@extends('layouts.adminlte')
@section('title', 'Add Knowledge Center Entry')
@section('page_title', 'Add Knowledge Center Entry')
@section('content')
<div class="card">
    <div class="card-body">
        @include('shared.flash')
        <form method="POST" action="{{ route('knowledge-center.store') }}" enctype="multipart/form-data">
            @csrf
            @include('knowledge-center._form')
        </form>
    </div>
</div>
@endsection

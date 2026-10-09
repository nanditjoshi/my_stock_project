@extends('layouts.adminlte')
@section('title', 'Edit Knowledge Center Entry')
@section('page_title', 'Edit Knowledge Center Entry')
@section('content')
<div class="card">
    <div class="card-body">
        @include('shared.flash')
        <form method="POST" action="{{ route('knowledge-center.update', $knowledgeCenter) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('knowledge-center._form')
        </form>
    </div>
</div>
@endsection

@extends('layouts.adminlte')
@section('title', 'Knowledge Center')
@section('page_title', 'Knowledge Center')
@section('content')
<div class="card">
    <div class="card-header">
        <a href="{{ route('knowledge-center.create') }}" class="btn btn-primary btn-sm">Add Knowledge Center Entry</a>
    </div>
    <div class="card-body">
        @include('shared.flash')
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>PMS ID</th>
                        <th>Images</th>
                        <th>Message 1</th>
                        <th>Message 2</th>
                        <th>Message 3</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($knowledgeCenters as $knowledgeCenter)
                        <tr>
                            <td>{{ $knowledgeCenter->name }}</td>
                            <td>{{ $knowledgeCenter->type }}</td>
                            <td>{{ $knowledgeCenter->PMS_id }}</td>
                            <td>
                                @foreach(array_filter(explode(',', $knowledgeCenter->images ?? '')) as $image)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image) }}" target="_blank" rel="noopener">View image</a>@if(!$loop->last), @endif
                                @endforeach
                            </td>
                            <td>{{ \Illuminate\Support\Str::limit($knowledgeCenter->message1, 100) }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($knowledgeCenter->message2, 100) }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($knowledgeCenter->message3, 100) }}</td>
                            <td>{{ $knowledgeCenter->updated_date }}</td>
                            <td class="text-nowrap">
                                <a class="btn btn-warning btn-sm" href="{{ route('knowledge-center.edit', $knowledgeCenter) }}">Edit</a>
                                <form class="d-inline" method="POST" action="{{ route('knowledge-center.destroy', $knowledgeCenter) }}" onsubmit="return confirm('Delete this Knowledge Center entry?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center">No Knowledge Center entries found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $knowledgeCenters->links() }}
    </div>
</div>
@endsection

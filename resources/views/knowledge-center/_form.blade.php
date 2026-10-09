<div class="form-group">
    <label for="name">Name</label>
    <input id="name" class="form-control" name="name" value="{{ old('name', $knowledgeCenter->name ?? '') }}" required maxlength="255">
    @error('name')<span class="text-danger">{{ $message }}</span>@enderror
</div>
<div class="form-group">
    <label for="type">Type</label>
    <input id="type" class="form-control" name="type" value="{{ old('type', $knowledgeCenter->type ?? '') }}" required maxlength="255">
    @error('type')<span class="text-danger">{{ $message }}</span>@enderror
</div>
<div class="form-group">
    <label for="PMS_id">PMS ID</label>
    <input id="PMS_id" class="form-control" name="PMS_id" value="{{ old('PMS_id', $knowledgeCenter->PMS_id ?? '') }}" maxlength="255">
    @error('PMS_id')<span class="text-danger">{{ $message }}</span>@enderror
</div>
@foreach([1, 2, 3] as $messageNumber)
    @php($field = 'message' . $messageNumber)
    <div class="form-group">
        <label for="{{ $field }}">Message {{ $messageNumber }}</label>
        <textarea id="{{ $field }}" class="form-control" name="{{ $field }}" rows="5">{{ old($field, $knowledgeCenter->$field ?? '') }}</textarea>
        @error($field)<span class="text-danger">{{ $message }}</span>@enderror
    </div>
@endforeach
<div class="form-group">
    <label for="images">Images</label>
    <input id="images" class="form-control-file" type="file" name="images[]" accept="image/*" multiple>
    <small class="form-text text-muted">Select one or more images. Each image can be up to 5 MB.</small>
    @error('images')<span class="text-danger d-block">{{ $message }}</span>@enderror
    @error('images.*')<span class="text-danger d-block">{{ $message }}</span>@enderror
</div>
@if(!empty($knowledgeCenter->images))
    <div class="form-group">
        <label>Existing Images</label>
        @foreach(array_filter(explode(',', $knowledgeCenter->images)) as $image)
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="images_to_remove[]" value="{{ $image }}" id="remove-image-{{ $loop->index }}">
                <label class="form-check-label" for="remove-image-{{ $loop->index }}">
                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image) }}" target="_blank" rel="noopener">{{ basename($image) }}</a>
                    (remove)
                </label>
            </div>
        @endforeach
    </div>
@endif
<button class="btn btn-primary">{{ isset($knowledgeCenter) ? 'Update' : 'Save' }}</button>
<a class="btn btn-default" href="{{ route('knowledge-center.index') }}">Back</a>

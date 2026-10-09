<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KnowledgeCenterController extends Controller
{
    public function index()
    {
        $knowledgeCenters = KnowledgeCenter::orderBy('name')->paginate(20);

        return view('knowledge-center.index', compact('knowledgeCenters'));
    }

    public function create()
    {
        return view('knowledge-center.create');
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $uploadedImages = $data['images'] ?? [];
        unset($data['images'], $data['images_to_remove']);

        

        $data['images'] = implode(',', $this->storeImages($uploadedImages)) ?: null;
        KnowledgeCenter::create($data);

        return redirect()->route('knowledge-center.index')->with('success', 'Knowledge Center entry created successfully.');
    }

    public function edit(KnowledgeCenter $knowledgeCenter)
    {
        return view('knowledge-center.edit', compact('knowledgeCenter'));
    }

    public function update(Request $request, KnowledgeCenter $knowledgeCenter)
    {
        $data = $this->validatedData($request);
        $uploadedImages = $data['images'] ?? [];
        $imagesToRemove = $data['images_to_remove'] ?? [];
        unset($data['images'], $data['images_to_remove']);

        $existingImages = $this->imagePaths($knowledgeCenter->images);
        $newImages = $this->storeImages($uploadedImages);
        if ($newImages) {
            $imagesToDelete = $existingImages;
            $data['images'] = implode(',', $newImages);
        } else {
            $imagesToDelete = array_intersect($existingImages, $imagesToRemove);
            $data['images'] = implode(',', array_values(array_diff($existingImages, $imagesToDelete))) ?: null;
        }

        $knowledgeCenter->update($data);
        Storage::disk('public')->delete($imagesToDelete);

        return redirect()->route('knowledge-center.index')->with('success', 'Knowledge Center entry updated successfully.');
    }

    public function destroy(KnowledgeCenter $knowledgeCenter)
    {
        Storage::disk('public')->delete($this->imagePaths($knowledgeCenter->images));
        $knowledgeCenter->delete();

        return redirect()->route('knowledge-center.index')->with('success', 'Knowledge Center entry deleted successfully.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'message1' => 'nullable|string',
            'message2' => 'nullable|string',
            'message3' => 'nullable|string',
            'PMS_id' => 'nullable|string|max:255',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
            'images_to_remove' => 'nullable|array',
            'images_to_remove.*' => 'string',
        ]);
    }

    private function storeImages(array $images): array
    {
        $paths = [];
        foreach ($images as $image) {
            $paths[] = $image->store('knowledge-center', 'public');
        }

        return $paths;
    }

    private function imagePaths(?string $images): array
    {
        return array_values(array_filter(explode(',', $images ?? '')));
    }
}

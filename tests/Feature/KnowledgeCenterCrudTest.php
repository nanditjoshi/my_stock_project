<?php

namespace Tests\Feature;

use App\Models\KnowledgeCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KnowledgeCenterCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_knowledge_center_entries_can_be_created_updated_and_deleted()
    {
        Storage::fake('public');
        $longMessage = str_repeat('Knowledge content ', 30) . 'end';

        $this->post(route('knowledge-center.store'), [
            'name' => 'Trading basics',
            'type' => 'Guide',
            'PMS_id' => 'PMS-101',
            'message1' => $longMessage,
            'message2' => 'Second message',
            'message3' => 'Third message',
            'images' => [
                UploadedFile::fake()->image('chart-one.jpg'),
                UploadedFile::fake()->image('chart-two.jpg'),
            ],
        ])->assertRedirect(route('knowledge-center.index'));

        $entry = KnowledgeCenter::firstOrFail();
        $imagePaths = explode(',', $entry->images);
        $this->assertCount(2, $imagePaths);
        $this->assertSame('PMS-101', $entry->PMS_id);
        foreach ($imagePaths as $imagePath) {
            Storage::disk('public')->assertExists($imagePath);
        }
        $this->assertSame($longMessage, $entry->message1);
        $this->assertNotNull($entry->created_date);
        $this->assertNotNull($entry->updated_date);

        $this->get(route('knowledge-center.index'))
            ->assertOk()
            ->assertSee('Trading basics');
        $this->get(route('knowledge-center.edit', $entry))
            ->assertOk()
            ->assertSee('Second message');

        $this->put(route('knowledge-center.update', $entry), [
            'name' => 'Updated trading basics',
            'type' => 'Reference',
            'PMS_id' => 'PMS-202',
            'message1' => 'Updated content',
            'message2' => '',
            'message3' => '',
            'images_to_remove' => [$imagePaths[0]],
            'images' => [UploadedFile::fake()->image('chart-three.jpg')],
        ])->assertRedirect(route('knowledge-center.index'));

        $entry->refresh();
        $updatedImagePaths = explode(',', $entry->images);
        $this->assertCount(1, $updatedImagePaths);
        $this->assertNotContains($imagePaths[0], $updatedImagePaths);
        $this->assertNotContains($imagePaths[1], $updatedImagePaths);
        Storage::disk('public')->assertMissing($imagePaths[0]);
        Storage::disk('public')->assertMissing($imagePaths[1]);
        foreach ($updatedImagePaths as $imagePath) {
            Storage::disk('public')->assertExists($imagePath);
        }
        $this->assertDatabaseHas('knowledge_center', [
            'id' => $entry->id,
            'name' => 'Updated trading basics',
            'type' => 'Reference',
            'PMS_id' => 'PMS-202',
            'message1' => 'Updated content',
        ]);

        $this->delete(route('knowledge-center.destroy', $entry))
            ->assertRedirect(route('knowledge-center.index'));

        $this->assertDatabaseMissing('knowledge_center', ['id' => $entry->id]);
        foreach ($updatedImagePaths as $imagePath) {
            Storage::disk('public')->assertMissing($imagePath);
        }
    }
}

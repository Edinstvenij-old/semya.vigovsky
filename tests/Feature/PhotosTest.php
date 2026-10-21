<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotosTest extends TestCase
{
    use RefreshDatabase;

    public function test_photos_in_chat_and_album(): void
    {
        Storage::fake('local');
        [$family, $mama] = $this->createFamily();
        $granny = $this->addMember($family, Role::Viewer);
        $thread = $this->actingAs($mama)->postJson('/api/threads', ['title' => 'Море'])->json();

        // Photo-only message (no text) is allowed.
        $message = $this->actingAs($mama)->post("/api/threads/{$thread['id']}/messages", [
            'files' => [UploadedFile::fake()->create('sea.jpg', 300, 'image/jpeg')],
            'thumbs' => [UploadedFile::fake()->create('sea-thumb.jpg', 20, 'image/jpeg')],
        ], ['Accept' => 'application/json'])->assertCreated()->json();
        $this->assertCount(1, $message['attachments']);
        $this->assertDatabaseHas('family_notifications', ['user_id' => $granny->id, 'body' => '📷 Фото']);

        // An empty message is still rejected.
        $this->actingAs($mama)->postJson("/api/threads/{$thread['id']}/messages", ['body' => ''])->assertStatus(422);

        // Task photo + a ticket scan: the album shows the photos but never the ticket.
        $task = $this->actingAs($mama)->postJson('/api/tasks', ['title' => 'Ремонт'])->json();
        $this->actingAs($mama)->post("/api/tasks/{$task['id']}/attachments", [
            'kind' => 'after', 'files' => [UploadedFile::fake()->create('done.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $album = $this->actingAs($granny)->getJson('/api/photos')->assertOk()->json('photos');
        $this->assertCount(2, $album);
        $this->assertSame('/tasks/'.$task['id'], $album[0]['source']['url']);
        $this->assertSame('Море', $album[1]['source']['label']);
        $this->actingAs($granny)->get($album[1]['url'])->assertOk();

        // Deleting the message removes its photo too.
        $this->actingAs($mama)->deleteJson("/api/threads/{$thread['id']}/messages/{$message['id']}")->assertOk();
        $this->assertCount(1, $this->actingAs($mama)->getJson('/api/photos')->json('photos'));

        [, $stranger] = $this->createFamily('Чужие');
        $this->actingAs($stranger)->getJson('/api/photos')->assertJsonCount(0, 'photos');
    }
}

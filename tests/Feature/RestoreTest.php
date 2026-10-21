<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\ChecklistItem;
use App\Models\Family;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Services\FamilyBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_then_restore_as_new_family(): void
    {
        Storage::fake('local');
        [$family, $mama] = $this->createFamily('Виговские');
        $son = $this->addMember($family);

        $task = $this->actingAs($mama)->postJson('/api/tasks', ['title' => 'Проводка', 'assignee_id' => $son->id, 'checklist' => ['Кабель', 'Автомат']])->json();
        $this->actingAs($mama)->post("/api/tasks/{$task['id']}/attachments", [
            'kind' => 'before',
            'files' => [UploadedFile::fake()->create('a.jpg', 50, 'image/jpeg')],
            'thumbs' => [UploadedFile::fake()->create('t.jpg', 5, 'image/jpeg')],
        ], ['Accept' => 'application/json'])->assertCreated();
        $thread = $this->actingAs($mama)->postJson('/api/threads', ['title' => 'Ужин', 'message' => 'В 18:00'])->json();
        $this->actingAs($mama)->postJson('/api/shopping', ['text' => 'Хлеб'])->assertCreated();

        $archive = app(FamilyBackup::class)->run($family);

        // Simulate losing one person's account entirely.
        $sonEmail = $son->email;
        DB::table('family_members')->where('user_id', $son->id)->delete();
        $son->delete();

        $this->artisan('family:restore', ['file' => $archive, '--name' => 'Виговские (копия)'])->assertSuccessful();

        $restored = Family::where('name', 'Виговские (копия)')->firstOrFail();
        $this->assertNotSame($family->id, $restored->id);

        $newTask = Task::withoutGlobalScopes()->where('family_id', $restored->id)->firstOrFail();
        $this->assertNotSame($task['id'], $newTask->id);
        $this->assertSame(2, ChecklistItem::where('task_id', $newTask->id)->count());

        // The missing person is re-created and linked again; the existing one is matched by email.
        $newSon = User::where('email', $sonEmail)->firstOrFail();
        $this->assertSame($newSon->id, $newTask->assignee_id);
        $this->assertTrue(DB::table('family_members')->where('family_id', $restored->id)->where('user_id', $mama->id)->exists());

        $photo = Attachment::withoutGlobalScopes()->where('family_id', $restored->id)->firstOrFail();
        $this->assertSame($newTask->id, $photo->attachable_id);
        $this->assertStringStartsWith("families/{$restored->id}/", $photo->path);
        Storage::disk('local')->assertExists([$photo->path, $photo->thumb_path]);

        $newThreadId = DB::table('threads')->where('family_id', $restored->id)->where('title', 'Ужин')->value('id');
        $this->assertSame('В 18:00', Message::where('thread_id', $newThreadId)->value('body'));
        $this->assertSame(1, DB::table('shopping_items')->where('family_id', $restored->id)->count());

        // The original family is untouched.
        $this->assertSame(1, Task::withoutGlobalScopes()->where('family_id', $family->id)->count());
        $this->assertNotNull($thread);
    }

    public function test_rejects_non_backup_archives(): void
    {
        $this->artisan('family:restore', ['file' => 'backups/nope.zip'])->assertFailed();
    }
}

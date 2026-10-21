<?php

namespace Tests\Feature;

use App\Models\FamilyNotification;
use App\Models\MaintenanceItem;
use App\Services\FamilyBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class ModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_with_checklist_links_and_photos(): void
    {
        Storage::fake('local');
        [$family, $admin] = $this->createFamily();
        $son = $this->addMember($family);

        $task = $this->actingAs($admin)->postJson('/api/tasks', [
            'title' => 'Проводка в гараже',
            'category_id' => $family->categories()->first()?->id,
            'assignee_id' => $son->id,
            'checklist' => ['Кабель ВВГнг 3×2.5', 'Автомат 16А'],
        ])->assertCreated()->json();

        $this->assertCount(2, $task['checklist']);
        $this->assertDatabaseHas('family_notifications', ['user_id' => $son->id, 'channel' => 'projects', 'urgent' => false]);

        $item = $task['checklist'][0]['id'];
        $this->actingAs($son)->patchJson("/api/tasks/{$task['id']}/checklist/{$item}", ['is_done' => true])
            ->assertOk()->assertJsonPath('is_done', true);

        $this->actingAs($son)->postJson("/api/tasks/{$task['id']}/links", ['url' => 'https://shop.example.com/cable', 'price' => 1200])
            ->assertCreated();

        $photo = $this->actingAs($son)->post("/api/tasks/{$task['id']}/attachments", [
            'kind' => 'before',
            'files' => [UploadedFile::fake()->create('before.jpg', 200, 'image/jpeg')],
        ], ['Accept' => 'application/json'])->assertCreated()->json('0');

        $this->actingAs($admin)->get($photo['url'])->assertOk();

        $this->actingAs($son)->postJson("/api/tasks/{$task['id']}/move", ['status' => 'done', 'order' => [$task['id']]])->assertOk();
        $this->actingAs($admin)->getJson("/api/tasks/{$task['id']}")
            ->assertJsonPath('status', 'done')
            ->assertJsonCount(1, 'links')
            ->assertJsonCount(1, 'attachments');
    }

    public function test_checkin_sends_urgent_notification_to_others(): void
    {
        [$family, $admin] = $this->createFamily();
        $mom = $this->addMember($family);

        $this->actingAs($admin)->postJson('/api/checkins', ['type' => 'border_crossed'])->assertCreated()
            ->assertJsonPath('label', 'Граница пройдена');

        $n = FamilyNotification::withoutGlobalScopes()->where('user_id', $mom->id)->first();
        $this->assertTrue($n->urgent);
        $this->assertSame('logistics', $n->channel);
        $this->assertDatabaseMissing('family_notifications', ['user_id' => $admin->id]);

        $this->actingAs($mom)->getJson('/api/dashboard')->assertOk()->assertJsonCount(1, 'statuses');
    }

    public function test_documents_require_two_factor(): void
    {
        Storage::fake('local');
        [, $admin] = $this->createFamily();

        $this->actingAs($admin)->getJson('/api/documents')->assertStatus(423)->assertJsonPath('two_factor', 'setup_required');

        $secret = $this->actingAs($admin)->postJson('/api/two-factor/setup', ['password' => 'password'])->assertOk()->json('secret');
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $codes = $this->actingAs($admin)->postJson('/api/two-factor/confirm', ['code' => $code])->assertOk()->json('recovery_codes');
        $this->assertCount(8, $codes);

        // Verified in this session → access granted.
        $doc = $this->actingAs($admin)->post('/api/documents', [
            'title' => 'Рейс Кишинёв — Тбилиси', 'type' => 'flight', 'reference' => 'H41234',
            'departs_at' => now()->addDays(3)->toDateTimeString(),
            'files' => [UploadedFile::fake()->create('boarding.pdf', 100, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertCreated()->json();

        $this->actingAs($admin)->getJson('/api/documents?q=H412')->assertOk()->assertJsonCount(1);

        // New session without the 2FA mark → challenge.
        $this->flushSession();
        $this->actingAs($admin)->getJson('/api/documents')->assertStatus(423)->assertJsonPath('two_factor', 'challenge_required');
        $this->actingAs($admin)->get($doc['attachments'][0]['url'])->assertStatus(423);

        // Recovery code works once.
        $this->actingAs($admin)->postJson('/api/two-factor/verify', ['code' => $codes[0]])->assertOk();
        $this->actingAs($admin)->getJson('/api/documents')->assertOk();
        $this->flushSession();
        $this->actingAs($admin)->postJson('/api/two-factor/verify', ['code' => $codes[0]])->assertStatus(422);
    }

    public function test_maintenance_completion_and_reminders(): void
    {
        [$family, $admin] = $this->createFamily();

        $item = $this->actingAs($admin)->postJson('/api/maintenance', [
            'title' => 'Оплатить электроэнергию', 'kind' => 'bill',
            'interval_days' => 30, 'next_due_on' => today()->addDays(2)->toDateString(), 'remind_days_before' => 3,
        ])->assertCreated()->json();

        $this->artisan('maintenance:remind')->assertSuccessful();
        $this->artisan('maintenance:remind')->assertSuccessful(); // no duplicate
        $this->assertSame(1, FamilyNotification::withoutGlobalScopes()->where('channel', 'maintenance')->count());

        $this->actingAs($admin)->postJson("/api/maintenance/{$item['id']}/complete", [
            'amount' => 2450.50, 'reading_day' => 12345, 'reading_night' => 6789,
        ])->assertCreated()->assertJsonPath('item.next_due_on', today()->addDays(30)->toDateString());

        $this->assertDatabaseHas('maintenance_logs', ['reading_night' => 6789]);
        $this->actingAs($admin)->getJson('/api/calendar?month='.today()->addMonth()->format('Y-m'))->assertOk();
        $this->assertNull(MaintenanceItem::first()->reminded_for);
    }

    public function test_threads_unread_and_mute(): void
    {
        [$family, $admin] = $this->createFamily();
        $dad = $this->addMember($family);

        $thread = $this->actingAs($admin)->postJson('/api/threads', ['title' => 'Визит к бабушке', 'message' => 'Когда едем?'])
            ->assertCreated()->json();

        $this->actingAs($dad)->getJson('/api/counts')->assertJsonPath('unread_messages', 1);
        $messages = $this->actingAs($dad)->getJson("/api/threads/{$thread['id']}/messages")->assertOk()->json('messages');
        $this->actingAs($dad)->postJson("/api/threads/{$thread['id']}/read", ['message_id' => end($messages)['id'], 'muted' => true])->assertOk();
        $this->actingAs($dad)->getJson('/api/counts')->assertJsonPath('unread_messages', 0);

        $this->actingAs($admin)->postJson("/api/threads/{$thread['id']}/messages", ['body' => 'В субботу'])->assertCreated();
        // Dad muted the thread → no new chat notification.
        $this->assertSame(1, FamilyNotification::withoutGlobalScopes()->where('user_id', $dad->id)->where('channel', 'chat')->count());

        $this->actingAs($dad)->getJson("/api/threads/{$thread['id']}/messages?after=".end($messages)['id'])
            ->assertJsonCount(1, 'messages');
    }

    public function test_backup_archive(): void
    {
        Storage::fake('local');
        [$family, $admin] = $this->createFamily();
        $this->actingAs($admin)->postJson('/api/tasks', ['title' => 'Что-то'])->assertCreated();

        $list = $this->actingAs($admin)->postJson('/api/family/backups')->assertCreated()->json();
        $this->assertCount(1, $list);

        $this->actingAs($admin)->get('/api/family/backups/'.$list[0]['name'])->assertOk();
        $this->assertFalse(app(FamilyBackup::class)->isDue($family->fresh()->forceFill(['backup_frequency' => 'weekly'])));
    }
}

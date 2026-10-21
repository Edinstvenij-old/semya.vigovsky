<?php

namespace Tests\Feature;

use App\Events\FamilyUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '1',
        ]);
        // Channels were registered at boot on the "null" broadcaster used by the test env.
        require base_path('routes/channels.php');
    }

    public function test_changes_emit_signals_but_reads_do_not(): void
    {
        Event::fake([FamilyUpdated::class]);
        [$family, $mama] = $this->createFamily();

        $this->actingAs($mama)->postJson('/api/shopping', ['text' => 'Хлеб'])->assertCreated();
        Event::assertDispatched(FamilyUpdated::class, fn (FamilyUpdated $e) => $e->type === 'shopping' && $e->familyId === $family->id);

        $task = $this->actingAs($mama)->postJson('/api/tasks', ['title' => 'Полка'])->json();
        Event::assertDispatched(FamilyUpdated::class, fn (FamilyUpdated $e) => $e->type === 'task' && $e->data['task_id'] === $task['id']);

        // Reading must not emit, otherwise open pages would reload each other forever.
        Event::fake([FamilyUpdated::class]);
        $this->actingAs($mama)->getJson("/api/tasks/{$task['id']}")->assertOk();
        $this->actingAs($mama)->getJson('/api/shopping')->assertOk();
        Event::assertNotDispatched(FamilyUpdated::class);

        // The signal carries no content, only the kind of change and ids.
        $event = new FamilyUpdated($family->id, 'message', ['thread_id' => 5]);
        $this->assertSame(['type' => 'message', 'thread_id' => 5], $event->broadcastWith());
        $this->assertSame("private-family.{$family->id}", $event->broadcastOn()[0]->name);
    }

    public function test_only_family_members_may_listen(): void
    {
        [$family, $mama] = $this->createFamily();
        [$other] = $this->createFamily('Чужие');

        $this->actingAs($mama)->post('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => "private-family.{$family->id}"])
            ->assertOk()->assertJsonStructure(['auth']);
        $this->actingAs($mama)->post('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => "private-family.{$other->id}"])
            ->assertForbidden();
    }

    public function test_config_exposes_realtime_only_when_reverb_is_set_up(): void
    {
        $this->getJson('/api/config')->assertJsonPath('realtime.key', 'test-key');

        config(['broadcasting.default' => 'null']);
        $this->getJson('/api/config')->assertJsonPath('realtime', null);
    }
}

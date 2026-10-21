<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Checkin;
use App\Models\FamilyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafetyCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_roll_call_flow(): void
    {
        [$family, $papa] = $this->createFamily();
        $mama = $this->addMember($family);
        $granny = $this->addMember($family, Role::Viewer);

        $check = $this->actingAs($papa)->postJson('/api/safety-checks', ['note' => 'Взрывы в центре'])
            ->assertCreated()->json('check');

        $this->assertSame(1, $check['answered']); // the asker counts as safe
        $this->assertSame(3, $check['total']);
        $this->assertTrue(FamilyNotification::withoutGlobalScopes()->where('user_id', $mama->id)->where('urgent', true)->exists());

        // Asking again does not create a second roll call.
        $this->actingAs($mama)->postJson('/api/safety-checks')->assertOk()->assertJsonPath('check.id', $check['id']);

        $this->actingAs($granny)->getJson('/api/counts')->assertJsonPath('safety_pending', $check['id']);

        // A viewer (grandmother) can answer even though she is read-only elsewhere.
        $this->actingAs($granny)->postJson("/api/safety-checks/{$check['id']}/respond", ['status' => 'shelter'])
            ->assertOk()->assertJsonPath('check.answered', 2);
        $this->actingAs($granny)->getJson('/api/counts')->assertJsonPath('safety_pending', null);
        $this->assertSame('shelter', Checkin::withoutGlobalScopes()->where('user_id', $granny->id)->value('type')->value);

        $this->actingAs($mama)->postJson("/api/safety-checks/{$check['id']}/respond", ['status' => 'help'])->assertOk();
        $members = $this->actingAs($papa)->getJson('/api/safety-checks/active')->json('check.members');
        $this->assertSame('help', $members[0]['status']); // "need help" is listed first

        // Only the asker or an admin closes it.
        $this->actingAs($mama)->postJson("/api/safety-checks/{$check['id']}/close")->assertForbidden();
        $this->actingAs($papa)->postJson("/api/safety-checks/{$check['id']}/close")->assertOk();
        $this->actingAs($papa)->getJson('/api/safety-checks/active')->assertJsonPath('check', null);
        $this->actingAs($granny)->postJson("/api/safety-checks/{$check['id']}/respond", ['status' => 'safe'])->assertStatus(422);
    }

    public function test_viewer_cannot_start_a_roll_call_and_families_are_isolated(): void
    {
        [$family, $papa] = $this->createFamily();
        $granny = $this->addMember($family, Role::Viewer);
        [, $stranger] = $this->createFamily('Чужие');

        $this->actingAs($granny)->postJson('/api/safety-checks')->assertForbidden();

        $id = $this->actingAs($papa)->postJson('/api/safety-checks')->json('check.id');
        $this->actingAs($stranger)->postJson("/api/safety-checks/{$id}/respond", ['status' => 'safe'])->assertNotFound();
    }
}

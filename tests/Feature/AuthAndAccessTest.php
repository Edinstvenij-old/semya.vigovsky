<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\FamilyMember;
use App\Models\Invitation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_visitor_can_create_a_family_then_registration_closes(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Анна', 'email' => 'anna@example.com', 'family_name' => 'Петровы',
            'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123',
        ])->assertOk()->assertJsonPath('user.role', 'admin')->assertJsonPath('user.family.name', 'Петровы');

        $this->assertDatabaseCount('task_categories', 4);

        $this->postJson('/api/logout');
        $this->getJson('/api/config')->assertJsonPath('registration_open', false);
        $this->postJson('/api/register', [
            'name' => 'X', 'email' => 'x@example.com', 'family_name' => 'Y',
            'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123',
        ])->assertForbidden();
    }

    public function test_passwords_are_hashed(): void
    {
        $user = User::factory()->create(['password' => 'plain-password-1']);
        $this->assertNotSame('plain-password-1', $user->getAttributes()['password']);
        $this->assertTrue(Hash::check('plain-password-1', $user->password));
    }

    public function test_families_are_isolated(): void
    {
        [, $alice] = $this->createFamily('A');
        [, $bob] = $this->createFamily('B');

        $this->actingAs($alice)->postJson('/api/tasks', ['title' => 'Секрет A'])->assertCreated();
        $taskId = Task::withoutGlobalScopes()->value('id');

        $this->actingAs($bob)->getJson('/api/tasks')->assertOk()->assertJsonCount(0);
        $this->actingAs($bob)->getJson("/api/tasks/{$taskId}")->assertNotFound();
        $this->actingAs($bob)->patchJson("/api/tasks/{$taskId}", ['title' => 'x'])->assertNotFound();
    }

    public function test_viewer_is_read_only(): void
    {
        [$family, $admin] = $this->createFamily();
        $viewer = $this->addMember($family, Role::Viewer);

        $this->actingAs($admin)->postJson('/api/tasks', ['title' => 'Купить фильтр'])->assertCreated();

        $this->actingAs($viewer)->getJson('/api/tasks')->assertOk()->assertJsonCount(1);
        $this->actingAs($viewer)->postJson('/api/tasks', ['title' => 'nope'])->assertForbidden();
        $this->actingAs($viewer)->postJson('/api/checkins', ['type' => 'arrived'])->assertForbidden();
        $this->actingAs($viewer)->getJson('/api/me')->assertJsonPath('user.can_write', false);
        // Personal actions still work.
        $this->actingAs($viewer)->postJson('/api/notifications/read')->assertOk();
    }

    public function test_only_admin_manages_family(): void
    {
        [$family] = $this->createFamily();
        $member = $this->addMember($family);

        $this->actingAs($member)->postJson('/api/family/invitations', ['role' => 'member'])->assertForbidden();
    }

    public function test_invitation_link_flow(): void
    {
        [$family, $admin] = $this->createFamily();

        $url = $this->actingAs($admin)->postJson('/api/family/invitations', ['role' => 'viewer', 'note' => 'Бабушка'])
            ->assertCreated()->json('url');
        $token = basename($url);

        $this->assertDatabaseMissing('invitations', ['token_hash' => $token]); // only the hash is stored

        auth()->guard('web')->logout();
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/invitations/{$token}")->assertOk()->assertJsonPath('role', 'viewer');
        $this->postJson("/api/invitations/{$token}/accept", [
            'name' => 'Бабушка', 'email' => 'granny@example.com', 'relation' => 'бабушка',
            'password' => 'granny-pass-123', 'password_confirmation' => 'granny-pass-123',
        ])->assertOk()->assertJsonPath('user.role', 'viewer');

        $granny = User::where('email', 'granny@example.com')->first();
        $this->assertSame(Role::Viewer, FamilyMember::where('user_id', $granny->id)->first()->role);

        // Single-use link is spent now.
        auth()->guard('web')->logout();
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/invitations/{$token}")->assertNotFound();
        $this->assertSame(1, Invitation::withoutGlobalScopes()->first()->uses);
    }

    public function test_last_admin_cannot_be_demoted(): void
    {
        [, $admin] = $this->createFamily();

        $this->actingAs($admin)->patchJson("/api/family/members/{$admin->id}", ['role' => 'member'])->assertStatus(422);
    }

    public function test_robots_and_noindex_headers(): void
    {
        $this->assertStringContainsString('Disallow: /', file_get_contents(public_path('robots.txt')));
        $this->withoutVite()->get('/')->assertSee('noindex', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet')
            // "no-referrer" would break Sanctum's stateful detection for same-origin GET requests.
            ->assertHeader('Referrer-Policy', 'same-origin');
    }
}

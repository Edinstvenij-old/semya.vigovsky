<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum treats requests from the SPA origin as stateful (session + CSRF).
        $this->withHeader('Referer', 'http://localhost/');
    }

    protected function createFamily(string $name = 'Ивановы'): array
    {
        $admin = User::factory()->create();
        $family = Family::create(['name' => $name, 'created_by' => $admin->id]);
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $admin->id, 'role' => Role::Admin]);
        $admin->update(['current_family_id' => $family->id]);
        $family->seedDefaults();

        return [$family, $admin];
    }

    protected function addMember(Family $family, Role $role = Role::Member): User
    {
        $user = User::factory()->create(['current_family_id' => $family->id]);
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }
}

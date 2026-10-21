<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_list(): void
    {
        [$family, $mama] = $this->createFamily();
        $son = $this->addMember($family);
        $granny = $this->addMember($family, Role::Viewer);

        $milk = $this->actingAs($mama)->postJson('/api/shopping', ['text' => 'Молоко', 'quantity' => '2 л'])->assertCreated()->json();
        $this->actingAs($mama)->postJson('/api/shopping', ['text' => 'Хлеб'])->assertCreated();
        $this->actingAs($son)->getJson('/api/counts')->assertJsonPath('shopping_open', 2);

        // The son in the shop ticks milk off; mum sees who bought it.
        $this->actingAs($son)->patchJson("/api/shopping/{$milk['id']}", ['is_done' => true])
            ->assertOk()->assertJsonPath('doer.id', $son->id);
        $list = $this->actingAs($mama)->getJson('/api/shopping')->json();
        $this->assertCount(1, $list['open']);
        $this->assertSame('Молоко', $list['done'][0]['text']);

        // Un-ticking clears who bought it.
        $this->actingAs($son)->patchJson("/api/shopping/{$milk['id']}", ['is_done' => false])->assertJsonPath('done_by', null);

        $this->actingAs($granny)->getJson('/api/shopping')->assertOk();
        $this->actingAs($granny)->postJson('/api/shopping', ['text' => 'x'])->assertForbidden();

        [, $stranger] = $this->createFamily('Чужие');
        $this->actingAs($stranger)->patchJson("/api/shopping/{$milk['id']}", ['is_done' => true])->assertNotFound();

        $this->actingAs($son)->patchJson("/api/shopping/{$milk['id']}", ['is_done' => true]);
        $this->actingAs($mama)->postJson('/api/shopping/clear-done')->assertOk();
        $this->assertCount(0, $this->actingAs($mama)->getJson('/api/shopping')->json('done'));
    }
}

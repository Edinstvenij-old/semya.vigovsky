<?php

namespace Tests\Feature;

use App\Models\FamilyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_recipient_gets_notifications_in_their_language(): void
    {
        [$family, $papa] = $this->createFamily();
        $mama = $this->addMember($family);
        $son = $this->addMember($family);
        $son->update(['preferences' => ['locale' => 'uk']]);

        $this->actingAs($papa)->postJson('/api/checkins', ['type' => 'border_crossed'])->assertCreated();

        $title = fn ($user) => FamilyNotification::withoutGlobalScopes()->where('user_id', $user->id)->value('title');
        $this->assertSame("{$papa->name}: Граница пройдена", $title($mama));
        $this->assertSame("{$papa->name}: Кордон пройдено", $title($son));
    }

    public function test_api_messages_follow_user_or_device_language(): void
    {
        // Guest: device language from the X-Locale header.
        $uk = $this->withHeader('X-Locale', 'uk')->postJson('/api/login', [])->assertStatus(422)->json('errors.email.0');
        $this->assertStringContainsString("обов'язковим", $uk);
        $ru = $this->withHeader('X-Locale', 'ru')->postJson('/api/login', [])->json('errors.email.0');
        $this->assertStringContainsString('обязательно', $ru);

        // Signed in: the saved preference wins.
        [, $papa] = $this->createFamily();
        $this->actingAs($papa)->patchJson('/api/profile/preferences', ['locale' => 'uk'])->assertOk();
        $this->actingAs($papa->fresh())->withHeader('X-Locale', 'ru')->postJson('/api/checkins', ['type' => 'safe'])
            ->assertCreated()->assertJsonPath('label', 'Усе добре, я в безпеці');

        $this->actingAs($papa->fresh())->patchJson('/api/profile/preferences', ['locale' => 'en'])->assertStatus(422);
    }
}

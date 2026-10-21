<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AirAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_status_for_watched_regions(): void
    {
        config(['familyhub.alerts.token' => 'T']);
        Cache::flush();
        Http::fake(['api.alerts.in.ua/*' => Http::response(['alerts' => [
            ['location_title' => 'Одеська область', 'location_type' => 'oblast', 'location_oblast' => 'Одеська область', 'alert_type' => 'air_raid', 'started_at' => '2026-10-21T08:00:00.000Z'],
            ['location_title' => 'Нікопольський район', 'location_type' => 'raion', 'location_oblast' => 'Дніпропетровська область', 'alert_type' => 'artillery_shelling', 'started_at' => '2026-10-21T07:00:00.000Z'],
        ]])]);

        [, $admin] = $this->createFamily();

        // Default region: Odesa.
        $this->actingAs($admin)->getJson('/api/alerts')->assertOk()
            ->assertJsonPath('regions.0.name', 'Одеська область')
            ->assertJsonPath('regions.0.status', 'alert')
            ->assertJsonPath('regions.0.types.0', 'Воздушная тревога');

        $this->actingAs($admin)->patchJson('/api/family', ['alert_regions' => ['Одеська область', 'Дніпропетровська область', 'м. Київ']])->assertOk();
        $this->actingAs($admin)->patchJson('/api/family', ['alert_regions' => ['Москва']])->assertStatus(422);

        $regions = $this->actingAs($admin)->getJson('/api/alerts')->json('regions');
        $this->assertSame(['alert', 'partial', 'calm'], array_column($regions, 'status'));
        $this->assertSame(['Нікопольський район'], $regions[1]['places']);

        // One API call thanks to the cache.
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer T'));
    }

    public function test_unreachable_service_and_missing_token(): void
    {
        [, $admin] = $this->createFamily();
        $this->actingAs($admin)->getJson('/api/alerts')->assertJsonPath('configured', false)->assertJsonPath('regions', []);

        config(['familyhub.alerts.token' => 'T']);
        Cache::flush();
        Http::fake(['api.alerts.in.ua/*' => Http::response('oops', 500)]);
        $this->actingAs($admin)->getJson('/api/alerts')->assertJsonPath('regions.0.status', 'unknown');
    }
}

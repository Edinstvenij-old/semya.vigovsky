<?php

namespace Tests\Feature;

use App\Models\TravelDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FlightStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_flight_status_with_delay(): void
    {
        config(['familyhub.flights.api_key' => 'K']);
        Cache::flush();
        Http::fake(['aerodatabox.p.rapidapi.com/*' => Http::response([[
            'number' => 'PS 9124', 'status' => 'Delayed', 'airline' => ['name' => 'Ukraine International'],
            'departure' => ['airport' => ['iata' => 'KIV', 'municipalityName' => 'Chisinau'],
                'scheduledTime' => ['utc' => '2026-10-24 10:00Z'], 'revisedTime' => ['utc' => '2026-10-24 10:45Z'], 'terminal' => '1', 'gate' => 'A3'],
            'arrival' => ['airport' => ['iata' => 'TBS', 'municipalityName' => 'Tbilisi'],
                'scheduledTime' => ['utc' => '2026-10-24 13:00Z'], 'baggageBelt' => '2'],
        ]])]);

        [, $admin] = $this->createFamily();
        $this->actingAs($admin)->withSession(['two_factor_verified_at' => now()->timestamp]);
        $admin->forceFill(['two_factor_secret' => 'X', 'two_factor_confirmed_at' => now()])->save();

        $doc = TravelDocument::create([
            'family_id' => $admin->current_family_id, 'type' => 'flight', 'title' => 'Кишинёв — Тбилиси',
            'reference' => 'PS 9124', 'departs_at' => '2026-10-24 13:00:00',
        ]);

        $this->getJson("/api/documents/{$doc->id}/flight-status")->assertOk()
            ->assertJsonPath('status_label', 'Задерживается')
            ->assertJsonPath('delay_minutes', 45)
            ->assertJsonPath('departure.gate', 'A3')
            ->assertJsonPath('arrival.baggage_belt', '2');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/flights/number/PS9124/2026-10-24') && $r->hasHeader('X-RapidAPI-Key', 'K'));
    }

    public function test_errors(): void
    {
        [, $admin] = $this->createFamily();
        $admin->forceFill(['two_factor_secret' => 'X', 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($admin)->withSession(['two_factor_verified_at' => now()->timestamp]);
        $doc = TravelDocument::create(['family_id' => $admin->current_family_id, 'type' => 'bus', 'title' => 'Автобус']);

        $this->getJson("/api/documents/{$doc->id}/flight-status")->assertStatus(422); // not configured

        config(['familyhub.flights.api_key' => 'K']);
        $this->getJson("/api/documents/{$doc->id}/flight-status")->assertStatus(422); // not a flight

        Http::fake(['aerodatabox.p.rapidapi.com/*' => Http::response([], 204)]);
        $doc->update(['type' => 'flight', 'reference' => 'XX 1', 'departs_at' => now()]);
        $this->getJson("/api/documents/{$doc->id}/flight-status")->assertNotFound();
    }
}

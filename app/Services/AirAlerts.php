<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Active air-raid and other alerts in Ukraine from alerts.in.ua (free token on request).
 * The whole country list is cached for 30 s, so any number of families and
 * page refreshes cost at most two API calls per minute.
 */
class AirAlerts
{
    /** Region names exactly as the API spells them. */
    public const REGIONS = [
        'Вінницька область', 'Волинська область', 'Дніпропетровська область', 'Донецька область',
        'Житомирська область', 'Закарпатська область', 'Запорізька область', 'Івано-Франківська область',
        'Київська область', 'Кіровоградська область', 'Луганська область', 'Львівська область',
        'Миколаївська область', 'Одеська область', 'Полтавська область', 'Рівненська область',
        'Сумська область', 'Тернопільська область', 'Харківська область', 'Херсонська область',
        'Хмельницька область', 'Черкаська область', 'Чернівецька область', 'Чернігівська область',
        'м. Київ', 'Автономна Республіка Крим',
    ];

    public const DEFAULT_REGIONS = ['Одеська область'];

    public const TYPES = [
        'air_raid' => 'Воздушная тревога',
        'artillery_shelling' => 'Угроза артобстрела',
        'urban_fights' => 'Уличные бои',
        'chemical' => 'Химическая угроза',
        'nuclear' => 'Радиационная угроза',
    ];

    public function configured(): bool
    {
        return (bool) config('familyhub.alerts.token');
    }

    /** @return array<int, array>|null null when the service is unreachable */
    public function active(): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        return Cache::remember('air-alerts:active', 30, function () {
            try {
                $response = Http::timeout(8)
                    ->withToken(config('familyhub.alerts.token'))
                    ->get('https://api.alerts.in.ua/v1/alerts/active.json');

                return $response->successful() ? ($response->json('alerts') ?? []) : null;
            } catch (Throwable $e) {
                Log::warning('alerts.in.ua unreachable', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    /** Status per watched region: alert for the whole oblast, partial (some districts) or calm. */
    public function forRegions(array $regions): array
    {
        $alerts = $this->active();

        return collect($regions)->map(function (string $region) use ($alerts) {
            if ($alerts === null) {
                return ['name' => $region, 'status' => 'unknown'];
            }

            $here = collect($alerts)->filter(fn ($a) => ($a['location_oblast'] ?? $a['location_title'] ?? null) === $region);
            if ($here->isEmpty()) {
                return ['name' => $region, 'status' => 'calm'];
            }

            $whole = $here->first(fn ($a) => ($a['location_type'] ?? '') === 'oblast' || ($a['location_title'] ?? '') === $region);

            return [
                'name' => $region,
                'status' => $whole ? 'alert' : 'partial',
                'since' => $here->min('started_at'),
                'types' => $here->pluck('alert_type')->unique()->map(fn ($t) => self::TYPES[$t] ?? $t)->values(),
                'places' => $whole ? [] : $here->pluck('location_title')->unique()->take(6)->values(),
            ];
        })->values()->all();
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Live flight status from AeroDataBox (RapidAPI; the free plan is enough for a family).
 * Answers are cached for 5 minutes per flight and date.
 */
class FlightStatus
{
    private const STATUSES = [
        'Expected' => 'По расписанию',
        'Scheduled' => 'По расписанию',
        'CheckIn' => 'Идёт регистрация',
        'Boarding' => 'Идёт посадка',
        'GateClosed' => 'Посадка закончена',
        'Departed' => 'Вылетел',
        'EnRoute' => 'В полёте',
        'Approaching' => 'Заходит на посадку',
        'Delayed' => 'Задерживается',
        'Arrived' => 'Прибыл',
        'Landed' => 'Приземлился',
        'Diverted' => 'Ушёл на запасной аэродром',
        'Canceled' => 'Отменён',
        'CanceledUncertain' => 'Возможно, отменён',
        'Unknown' => 'Нет данных',
    ];

    public function configured(): bool
    {
        return (bool) config('familyhub.flights.api_key');
    }

    /** @return array|null null = flight not found or the service is unavailable */
    public function lookup(string $flightNumber, Carbon $date): ?array
    {
        $number = strtoupper(preg_replace('/\s+/', '', $flightNumber));
        if (! $this->configured() || ! preg_match('/^[A-Z0-9]{2,3}\d{1,4}[A-Z]?$/', $number)) {
            return null;
        }

        return Cache::remember("flight:{$number}:{$date->toDateString()}", 300, function () use ($number, $date) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'X-RapidAPI-Key' => config('familyhub.flights.api_key'),
                        'X-RapidAPI-Host' => 'aerodatabox.p.rapidapi.com',
                    ])
                    ->get("https://aerodatabox.p.rapidapi.com/flights/number/{$number}/{$date->toDateString()}");
            } catch (Throwable) {
                return null;
            }

            $flight = $response->successful() ? ($response->json()[0] ?? null) : null;

            return $flight ? $this->normalize($flight) : null;
        });
    }

    private function normalize(array $f): array
    {
        $leg = function (?array $leg) {
            $time = fn (?array $t, string $legacy) => $t['utc'] ?? $t['local'] ?? $leg[$legacy] ?? null;

            return [
                'airport' => trim(($leg['airport']['iata'] ?? '').' '.($leg['airport']['municipalityName'] ?? $leg['airport']['name'] ?? '')),
                'scheduled' => $time($leg['scheduledTime'] ?? null, 'scheduledTimeUtc'),
                'revised' => $time($leg['revisedTime'] ?? null, 'actualTimeUtc') ?? $time($leg['runwayTime'] ?? null, 'runwayTimeUtc'),
                'terminal' => $leg['terminal'] ?? null,
                'gate' => $leg['gate'] ?? null,
                'baggage_belt' => $leg['baggageBelt'] ?? null,
            ];
        };

        $departure = $leg($f['departure'] ?? null);
        $arrival = $leg($f['arrival'] ?? null);
        $delay = ($departure['scheduled'] && $departure['revised'])
            ? (int) round(Carbon::parse($departure['scheduled'])->diffInMinutes(Carbon::parse($departure['revised']), false))
            : null;

        $status = $f['status'] ?? 'Unknown';

        return [
            'number' => $f['number'] ?? null,
            'airline' => $f['airline']['name'] ?? null,
            'status' => $status,
            'status_label' => self::STATUSES[$status] ?? $status,
            'delay_minutes' => $delay !== null && $delay > 0 ? $delay : 0,
            'departure' => $departure,
            'arrival' => $arrival,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}

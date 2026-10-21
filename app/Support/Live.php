<?php

namespace App\Support;

use App\Events\FamilyUpdated;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fire-and-forget realtime signal. If the websocket server is down, the
 * request must still succeed — apps fall back to polling anyway.
 */
class Live
{
    public static function emit(?int $familyId, string $type, array $data = []): void
    {
        if (! $familyId || in_array(config('broadcasting.default'), ['null', 'log'], true)) {
            return;
        }

        try {
            event(new FamilyUpdated($familyId, $type, $data));
        } catch (Throwable $e) {
            Log::warning('Realtime broadcast failed', ['type' => $type, 'error' => $e->getMessage()]);
        }
    }
}

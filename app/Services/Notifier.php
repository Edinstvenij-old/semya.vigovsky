<?php

namespace App\Services;

use App\Jobs\SendWebPush;
use App\Models\FamilyNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Smart" notifications. Each notification gets a channel:
 *  - logistics   → urgent: push with sound / vibration, stays on screen
 *  - projects, maintenance, chat, family → silent push, just a badge
 * Users can switch push off per channel in their preferences;
 * the in-app notification list always receives everything.
 */
class Notifier
{
    private const URGENT_CHANNELS = ['logistics'];

    /**
     * @param  iterable<User>|null  $recipients  null = whole family
     */
    public function send(
        int $familyId,
        string $channel,
        string $title,
        ?string $body = null,
        ?string $url = null,
        ?User $actor = null,
        ?iterable $recipients = null,
        ?bool $urgent = null,
    ): void {
        $urgent ??= in_array($channel, self::URGENT_CHANNELS, true);

        $recipients ??= User::whereIn('id', DB::table('family_members')->where('family_id', $familyId)->pluck('user_id'))->get();

        foreach ($recipients as $user) {
            if ($actor && $user->id === $actor->id) {
                continue;
            }

            $notification = FamilyNotification::create([
                'family_id' => $familyId,
                'user_id' => $user->id,
                'channel' => $channel,
                'urgent' => $urgent,
                'title' => mb_substr($title, 0, 250),
                'body' => $body ? mb_substr($body, 0, 250) : null,
                'url' => $url,
            ]);

            if ($user->pref('push_'.$channel) !== false && config('familyhub.vapid.public_key')) {
                SendWebPush::dispatch($user->id, [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'url' => $url ?? '/',
                    'urgent' => $urgent,
                    'channel' => $channel,
                ]);
            }
        }
    }
}

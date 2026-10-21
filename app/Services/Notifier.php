<?php

namespace App\Services;

use App\Jobs\SendTelegram;
use App\Jobs\SendWebPush;
use App\Models\FamilyNotification;
use App\Models\User;
use App\Support\Live;
use App\Support\Msg;
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
     * Title and body are plain strings (user content) or Msg::t() arrays, which are
     * rendered in each recipient's own language.
     *
     * @param  iterable<User>|null  $recipients  null = whole family
     */
    public function send(
        int $familyId,
        string $channel,
        string|array $title,
        string|array|null $body = null,
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

            $locale = $user->pref('locale') ?: 'ru';
            $text = Msg::render($title, $locale);
            $details = Msg::render($body, $locale);

            $notification = FamilyNotification::create([
                'family_id' => $familyId,
                'user_id' => $user->id,
                'channel' => $channel,
                'urgent' => $urgent,
                'title' => mb_substr($text, 0, 250),
                'body' => $details ? mb_substr($details, 0, 250) : null,
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

            if ($user->telegram_chat_id && config('familyhub.telegram.token') && ($urgent || $user->pref('telegram_all'))) {
                SendTelegram::dispatch($user->telegram_chat_id, $notification->title, $notification->body, $url, $urgent, $locale);
            }
        }

        // Open apps refresh their badges (and show the urgent banner) right away.
        Live::emit($familyId, 'notification', ['urgent' => $urgent]);
    }
}

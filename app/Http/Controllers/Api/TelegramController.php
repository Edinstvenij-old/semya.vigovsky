<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Telegram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Linking flow: the app shows a t.me/<bot>?start=<code> link; the user taps
 * "Start" in Telegram, Telegram calls our webhook with "/start <code>", and
 * we remember that chat for the user. The code is single-use and short-lived.
 */
class TelegramController extends Controller
{
    public function __construct(private Telegram $telegram) {}

    public function link(Request $request): JsonResponse
    {
        abort_unless($this->telegram->configured() && $this->telegram->botUsername(), 422, 'Telegram-бот ещё не настроен администратором сервера.');

        $code = Str::random(24);
        Cache::put("telegram-link:{$code}", $request->user()->id, now()->addMinutes(15));

        return response()->json(['url' => 'https://t.me/'.$this->telegram->botUsername().'?start='.$code]);
    }

    public function unlink(Request $request): JsonResponse
    {
        $request->user()->forceFill(['telegram_chat_id' => null])->save();

        return response()->json(['ok' => true]);
    }

    /** Called by Telegram. Authenticated by the secret token set with `php artisan telegram:webhook`. */
    public function webhook(Request $request): JsonResponse
    {
        $secret = (string) config('familyhub.telegram.webhook_secret');
        abort_unless($secret !== '' && hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        $chatId = (string) $request->input('message.chat.id');
        $text = trim((string) $request->input('message.text'));
        if ($chatId === '' || $request->input('message.chat.type') !== 'private') {
            return response()->json(['ok' => true]);
        }

        if (preg_match('/^\/start\s+(\S+)$/', $text, $m)) {
            $userId = Cache::pull("telegram-link:{$m[1]}");
            $user = $userId ? User::find($userId) : null;

            if ($user) {
                User::where('telegram_chat_id', $chatId)->update(['telegram_chat_id' => null]);
                $user->forceFill(['telegram_chat_id' => $chatId])->save();
                $this->telegram->sendMessage($chatId, 'Готово, '.Telegram::escape($user->name).'! Сюда будут приходить срочные новости семьи: статусы в пути и «все ли в порядке?». Отключить: /stop');
            } else {
                $this->telegram->sendMessage($chatId, 'Ссылка устарела. Откройте приложение → Настройки → «Подключить Telegram» ещё раз.');
            }
        } elseif ($text === '/stop') {
            User::where('telegram_chat_id', $chatId)->update(['telegram_chat_id' => null]);
            $this->telegram->sendMessage($chatId, 'Уведомления отключены. Подключить снова можно в настройках приложения.');
        } else {
            $this->telegram->sendMessage($chatId, 'Это бот семейного приложения. Подключение — в приложении: Настройки → «Подключить Telegram».');
        }

        return response()->json(['ok' => true]);
    }
}

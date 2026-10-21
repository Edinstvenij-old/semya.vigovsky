<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Telegram Bot API client. Telegram is the fallback channel for urgent
 * family statuses: browser push is unreliable on iPhones and old phones,
 * while almost everyone in the family already has Telegram.
 */
class Telegram
{
    public function configured(): bool
    {
        return (bool) config('familyhub.telegram.token');
    }

    public function botUsername(): ?string
    {
        return config('familyhub.telegram.username');
    }

    public function sendMessage(string $chatId, string $html, ?string $buttonUrl = null, bool $silent = false): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $html,
            'parse_mode' => 'HTML',
            'disable_notification' => $silent,
            'link_preview_options' => ['is_disabled' => true],
        ];

        // Telegram only accepts public https links on buttons.
        if ($buttonUrl && str_starts_with($buttonUrl, 'https://')) {
            $payload['reply_markup'] = ['inline_keyboard' => [[['text' => 'Открыть в приложении', 'url' => $buttonUrl]]]];
        }

        $response = $this->call('sendMessage', $payload);

        return (bool) ($response['ok'] ?? false);
    }

    public function setWebhook(string $url, string $secret): array
    {
        return $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
            'drop_pending_updates' => true,
        ]);
    }

    private function call(string $method, array $payload): array
    {
        $token = config('familyhub.telegram.token');
        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/{$method}", $payload);

        if (! $response->successful()) {
            Log::warning('Telegram API error', ['method' => $method, 'status' => $response->status(), 'body' => $response->json('description')]);
        }

        return $response->json() ?? [];
    }

    public static function escape(?string $text): string
    {
        return htmlspecialchars((string) $text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

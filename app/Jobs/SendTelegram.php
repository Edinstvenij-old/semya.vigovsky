<?php

namespace App\Jobs;

use App\Services\Telegram;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTelegram implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 20;

    public function __construct(
        public string $chatId,
        public string $title,
        public ?string $body,
        public ?string $url,
        public bool $urgent,
        public string $locale = 'ru',
    ) {}

    public function handle(Telegram $telegram): void
    {
        $text = ($this->urgent ? '🔔 ' : '').'<b>'.Telegram::escape($this->title).'</b>';
        if ($this->body) {
            $text .= "\n".Telegram::escape($this->body);
        }

        $telegram->sendMessage(
            $this->chatId,
            $text,
            $this->url ? rtrim(config('app.url'), '/').$this->url : null,
            silent: ! $this->urgent,
            locale: $this->locale,
        );
    }
}

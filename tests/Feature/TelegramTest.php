<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'familyhub.telegram.token' => 'TEST-TOKEN',
            'familyhub.telegram.username' => 'vigovsky_bot',
            'familyhub.telegram.webhook_secret' => 'hook-secret',
            'app.url' => 'https://semya.example.com',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    }

    private function webhook(array $message, string $secret = 'hook-secret')
    {
        return $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $secret)
            ->postJson('/api/telegram/webhook', ['message' => $message + ['chat' => ['id' => 555, 'type' => 'private']]]);
    }

    public function test_link_and_receive_urgent_only_by_default(): void
    {
        [$family, $papa] = $this->createFamily();
        $son = $this->addMember($family);

        $url = $this->actingAs($son)->postJson('/api/telegram/link')->assertOk()->json('url');
        $this->assertStringStartsWith('https://t.me/vigovsky_bot?start=', $url);
        $code = substr($url, strpos($url, '=') + 1);

        $this->webhook(['text' => "/start {$code}"], 'wrong')->assertForbidden();
        $this->webhook(['text' => "/start {$code}"])->assertOk();
        $this->assertSame('555', $son->fresh()->telegram_chat_id);
        $this->actingAs($son->fresh())->getJson('/api/me')->assertJsonPath('user.telegram_linked', true);

        // The code is single-use.
        $this->webhook(['text' => "/start {$code}", 'chat' => ['id' => 777, 'type' => 'private']]);
        $this->assertSame('555', $son->fresh()->telegram_chat_id);

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        // Urgent status → Telegram with sound and an https button.
        $this->actingAs($papa)->postJson('/api/checkins', ['type' => 'border_crossed'])->assertCreated();
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/botTEST-TOKEN/sendMessage')
            && $r['chat_id'] === '555'
            && $r['disable_notification'] === false
            && str_contains($r['text'], 'Граница пройдена')
            && $r['reply_markup']['inline_keyboard'][0][0]['url'] === 'https://semya.example.com/travel');

        // Non-urgent (task assigned) is not sent unless the user opts in.
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $this->actingAs($papa)->postJson('/api/tasks', ['title' => 'Купить фильтр', 'assignee_id' => $son->id])->assertCreated();
        Http::assertNothingSent();

        $this->actingAs($son)->patchJson('/api/profile/preferences', ['telegram_all' => true])->assertOk();
        $this->actingAs($papa)->postJson('/api/tasks', ['title' => 'Ещё задача', 'assignee_id' => $son->id])->assertCreated();
        Http::assertSent(fn (HttpRequest $r) => $r['disable_notification'] === true);

        // /stop unlinks.
        $this->webhook(['text' => '/stop'])->assertOk();
        $this->assertNull($son->fresh()->telegram_chat_id);
    }

    public function test_link_requires_configured_bot(): void
    {
        config(['familyhub.telegram.token' => null]);
        [, $papa] = $this->createFamily();

        $this->actingAs($papa)->postJson('/api/telegram/link')->assertStatus(422);
        $this->webhook(['text' => '/start x'])->assertOk(); // secret still valid; nothing to do
    }
}

<?php

namespace App\Jobs;

use App\Models\PushSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class SendWebPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $userId, public array $payload) {}

    public function handle(): void
    {
        $subscriptions = PushSubscription::where('user_id', $this->userId)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => config('familyhub.vapid.subject'),
            'publicKey' => config('familyhub.vapid.public_key'),
            'privateKey' => config('familyhub.vapid.private_key'),
        ]], [
            // Urgent logistics statuses are delivered immediately even on battery saver.
            'TTL' => $this->payload['urgent'] ? 3600 : 86400,
            'urgency' => $this->payload['urgent'] ? 'high' : 'low',
        ]);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->public_key,
                'authToken' => $sub->auth_token,
                'contentEncoding' => $sub->content_encoding,
            ]), json_encode($this->payload));
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            } elseif (! $report->isSuccess()) {
                Log::warning('Web push failed', ['reason' => $report->getReason()]);
            }
        }
    }
}

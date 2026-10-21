<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyNotification;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            FamilyNotification::where('user_id', $request->user()->id)->latest('id')->limit(100)->get()
        );
    }

    public function markRead(Request $request): JsonResponse
    {
        FamilyNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->when($request->input('ids'), fn ($q, $ids) => $q->whereIn('id', (array) $ids))
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:1024'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $request->user()->id,
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint', $request->input('endpoint'))
            ->delete();

        return response()->json(['ok' => true]);
    }
}

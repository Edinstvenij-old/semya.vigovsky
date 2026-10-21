<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Message;
use App\Models\Task;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;

/**
 * Family photo album: every photo from tasks (before/after) and chats in one place.
 * Ticket scans ("document" kind) never appear here — they stay behind 2FA.
 */
class PhotoController extends Controller
{
    public function index(): JsonResponse
    {
        $page = Attachment::query()
            ->where('kind', '!=', 'document')
            ->where('mime', 'like', 'image/%')
            ->with(['uploader:id,name', 'attachable' => fn (MorphTo $morph) => $morph->morphWith([
                Message::class => ['thread:id,title'],
            ])])
            ->orderByDesc('id')
            ->cursorPaginate(48);

        return response()->json([
            'photos' => collect($page->items())->map(fn (Attachment $a) => [
                'id' => $a->id,
                'url' => $a->url,
                'thumb_url' => $a->thumb_url,
                'name' => $a->original_name,
                'kind' => $a->kind,
                'created_at' => $a->created_at,
                'uploader' => $a->uploader?->name,
                'source' => $this->source($a),
            ]),
            'next_cursor' => $page->nextCursor()?->encode(),
        ]);
    }

    private function source(Attachment $a): ?array
    {
        return match (true) {
            $a->attachable instanceof Task => ['label' => $a->attachable->title, 'url' => "/tasks/{$a->attachable->id}"],
            $a->attachable instanceof Message => ['label' => $a->attachable->thread?->title, 'url' => "/threads/{$a->attachable->thread_id}"],
            default => null,
        };
    }
}

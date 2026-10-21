<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\FamilyMember;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Services\Notifier;
use App\Support\FamilyContext;
use App\Support\Live;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ThreadController extends Controller
{
    public function __construct(private FamilyContext $context, private Notifier $notifier) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $threads = Thread::with(['lastMessage.user:id,name', 'task:id,title,status'])
            ->leftJoin('thread_reads', fn ($j) => $j->on('thread_reads.thread_id', '=', 'threads.id')->where('thread_reads.user_id', $userId))
            ->select('threads.*', DB::raw('coalesce(thread_reads.muted, false) as muted'))
            ->selectSub(
                Message::selectRaw('count(*)')
                    ->whereColumn('messages.thread_id', 'threads.id')
                    ->whereRaw('messages.id > coalesce(thread_reads.last_read_message_id, 0)')
                    ->where('messages.user_id', '!=', $userId),
                'unread_count'
            )
            ->orderByDesc('is_pinned')
            ->orderByRaw('coalesce(last_message_at, threads.created_at) desc')
            ->get();

        return response()->json($threads);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'icon' => ['nullable', 'string', 'max:40'],
            'trip_id' => ['nullable', Rule::exists('trips', 'id')->where('family_id', $this->context->id())],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        // A trip has a single discussion thread: reuse it if it already exists.
        if (! empty($data['trip_id']) && $existing = Thread::where('trip_id', $data['trip_id'])->first()) {
            return response()->json($existing);
        }

        $thread = Thread::create(collect($data)->except('message')->all() + ['created_by' => $request->user()->id]);

        if (! empty($data['message'])) {
            $this->postMessage($thread, $request->user(), $data['message']);
        }

        return response()->json($thread, 201);
    }

    public function update(Request $request, Thread $thread): JsonResponse
    {
        $thread->update($request->validate([
            'title' => ['sometimes', 'string', 'max:150'],
            'icon' => ['sometimes', 'string', 'max:40'],
            'is_pinned' => ['sometimes', 'boolean'],
        ]));

        return response()->json($thread);
    }

    public function destroy(Request $request, Thread $thread): JsonResponse
    {
        abort_unless($thread->created_by === $request->user()->id || $this->context->isAdmin(), 403);
        $thread->delete();

        return response()->json(['ok' => true]);
    }

    /** Messages, newest page by default; ?after=<id> returns only new ones (polling). */
    public function messages(Request $request, Thread $thread): JsonResponse
    {
        $query = $thread->messages()->with('user:id,name,avatar_color', 'attachments');

        if ($after = $request->integer('after')) {
            $messages = $query->where('id', '>', $after)->orderBy('id')->limit(200)->get();
        } else {
            $messages = $query
                ->when($request->integer('before'), fn ($q, $before) => $q->where('id', '<', $before))
                ->orderByDesc('id')->limit(50)->get()->reverse()->values();
        }

        return response()->json([
            'thread' => $thread->load('task:id,title,status', 'trip:id,title'),
            'messages' => $messages,
            'muted' => (bool) DB::table('thread_reads')
                ->where('thread_id', $thread->id)->where('user_id', $request->user()->id)->value('muted'),
        ]);
    }

    public function storeMessage(Request $request, Thread $thread): JsonResponse
    {
        $request->validate([
            'body' => ['nullable', 'required_without:files', 'string', 'max:5000'],
            'files' => ['sometimes', 'array', 'max:10'],
            'files.*' => ['file', 'max:'.config('familyhub.max_upload_kb'), 'mimes:jpg,jpeg,png,webp,heic,heif,gif'],
            'thumbs' => ['sometimes', 'array'],
            'thumbs.*' => ['file', 'max:1024', 'mimes:jpg,jpeg,webp'],
        ]);

        $message = $this->postMessage($thread, $request->user(), (string) $request->input('body', ''), $request->file('files', []), $request->file('thumbs', []));

        return response()->json($message->load('user:id,name,avatar_color', 'attachments'), 201);
    }

    public function destroyMessage(Request $request, Thread $thread, Message $message): JsonResponse
    {
        abort_unless($message->thread_id === $thread->id, 404);
        abort_unless($message->user_id === $request->user()->id || $this->context->isAdmin(), 403);
        $message->delete();
        Live::emit($thread->family_id, 'message', ['thread_id' => $thread->id]);

        return response()->json(['ok' => true]);
    }

    /** Read marker and mute switch — allowed for viewers too. */
    public function markRead(Request $request, Thread $thread): JsonResponse
    {
        $data = $request->validate([
            'message_id' => ['sometimes', 'integer'],
            'muted' => ['sometimes', 'boolean'],
        ]);

        $values = [];
        if (array_key_exists('message_id', $data)) {
            $values['last_read_message_id'] = $data['message_id'];
        }
        if (array_key_exists('muted', $data)) {
            $values['muted'] = $data['muted'];
        }

        if ($values) {
            DB::table('thread_reads')->updateOrInsert(
                ['thread_id' => $thread->id, 'user_id' => $request->user()->id],
                $values,
            );
        }

        return response()->json(['ok' => true]);
    }

    private function postMessage(Thread $thread, User $author, string $body, array $files = [], array $thumbs = []): Message
    {
        $message = $thread->messages()->create(['user_id' => $author->id, 'body' => $body]);
        foreach ($files as $i => $file) {
            Attachment::store($message, $file, 'other', $author->id, $thumbs[$i] ?? null);
        }
        $thread->update(['last_message_at' => now()]);

        DB::table('thread_reads')->updateOrInsert(
            ['thread_id' => $thread->id, 'user_id' => $author->id],
            ['last_read_message_id' => $message->id],
        );

        $muted = DB::table('thread_reads')->where('thread_id', $thread->id)->where('muted', true)->pluck('user_id');
        $recipients = User::whereIn('id', FamilyMember::where('family_id', $thread->family_id)->pluck('user_id'))
            ->whereNotIn('id', $muted)
            ->get();

        $this->notifier->send(
            $thread->family_id, 'chat', "{$author->name} в «{$thread->title}»",
            $body !== '' ? Str::limit($body, 140) : '📷 Фото', "/threads/{$thread->id}", $author, $recipients,
        );

        Live::emit($thread->family_id, 'message', ['thread_id' => $thread->id]);

        return $message;
    }
}

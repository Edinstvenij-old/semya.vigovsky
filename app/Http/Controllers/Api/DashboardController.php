<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\FamilyMember;
use App\Models\FamilyNotification;
use App\Models\MaintenanceItem;
use App\Models\Message;
use App\Models\SafetyCheck;
use App\Models\ShoppingItem;
use App\Models\Task;
use App\Models\Thread;
use App\Support\FamilyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private FamilyContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $memberIds = FamilyMember::where('family_id', $this->context->id())->pluck('user_id');

        return response()->json([
            'statuses' => Checkin::with('user:id,name,avatar_color')
                ->whereIn('id', Checkin::selectRaw('max(id)')->whereIn('user_id', $memberIds)->groupBy('user_id'))
                ->where('created_at', '>=', now()->subDays(14))
                ->latest()
                ->get(),
            'my_tasks' => Task::with('category:id,name,color,icon')
                ->where('assignee_id', $user->id)
                ->where('status', '!=', 'done')
                ->orderByRaw('due_date is null')->orderBy('due_date')
                ->limit(8)->get(),
            'maintenance_due' => MaintenanceItem::where('next_due_on', '<=', now()->addDays(14))
                ->orderBy('next_due_on')->limit(8)->get(),
            'counts' => $this->counts($request)->getData(true),
        ]);
    }

    /** Lightweight badge counters, polled by the SPA every ~20 seconds. */
    public function counts(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $unreadMessages = Message::query()
            ->join('threads', 'threads.id', '=', 'messages.thread_id')
            ->leftJoin('thread_reads', fn ($j) => $j->on('thread_reads.thread_id', '=', 'threads.id')->where('thread_reads.user_id', $userId))
            ->where('threads.family_id', $this->context->id())
            ->where('messages.user_id', '!=', $userId)
            ->whereRaw('messages.id > coalesce(thread_reads.last_read_message_id, 0)')
            ->where(fn ($q) => $q->whereNull('thread_reads.muted')->orWhere('thread_reads.muted', false))
            ->count();

        $notifications = FamilyNotification::where('user_id', $userId)->whereNull('read_at');

        return response()->json([
            'unread_messages' => $unreadMessages,
            'unread_notifications' => (clone $notifications)->count(),
            'latest_urgent' => (clone $notifications)->where('urgent', true)->latest('id')->first(['id', 'title', 'body', 'url', 'created_at']),
            'threads' => Thread::count(),
            'open_tasks' => Task::where('status', '!=', 'done')->count(),
            'shopping_open' => ShoppingItem::where('is_done', false)->count(),
            'overdue_maintenance' => MaintenanceItem::where('next_due_on', '<', today())->count(),
            // Open "is everyone OK?" roll call this user has not answered yet.
            'safety_pending' => SafetyCheck::open()
                ->whereDoesntHave('responses', fn ($q) => $q->where('user_id', $userId))
                ->latest('id')->value('id'),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}

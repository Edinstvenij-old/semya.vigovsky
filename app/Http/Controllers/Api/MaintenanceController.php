<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceItem;
use App\Models\Task;
use App\Models\Trip;
use App\Support\FamilyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class MaintenanceController extends Controller
{
    public function __construct(private FamilyContext $context) {}

    public function index(): JsonResponse
    {
        return response()->json(
            // Eager-load limits apply per item (window function), so this is the last 5 logs of each.
            MaintenanceItem::with(['assignee:id,name,avatar_color', 'logs' => fn ($q) => $q->with('doer:id,name')->limit(5)])
                ->orderBy('next_due_on')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(MaintenanceItem::create($this->validated($request, true)), 201);
    }

    public function update(Request $request, MaintenanceItem $item): JsonResponse
    {
        $data = $this->validated($request, false);
        if (isset($data['next_due_on'])) {
            $data['reminded_for'] = null;
        }
        $item->update($data);

        return response()->json($item);
    }

    public function destroy(MaintenanceItem $item): JsonResponse
    {
        $item->delete();

        return response()->json(['ok' => true]);
    }

    public function complete(Request $request, MaintenanceItem $item): JsonResponse
    {
        $data = $request->validate([
            'done_on' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'reading_day' => ['nullable', 'numeric', 'min:0'],
            'reading_night' => ['nullable', 'numeric', 'min:0'],
        ]);

        $log = $item->complete($request->user(), array_filter($data, fn ($v) => $v !== null));

        return response()->json(['item' => $item->fresh(), 'log' => $log], 201);
    }

    /** Calendar events for a month: maintenance due dates, task deadlines and trips. */
    public function calendar(Request $request): JsonResponse
    {
        $month = Carbon::parse($request->input('month', now()->format('Y-m')).'-01');
        $from = $month->copy()->startOfMonth()->startOfWeek();
        $to = $month->copy()->endOfMonth()->endOfWeek();

        $events = collect();

        // Recurring items: project each one forward through the visible range.
        foreach (MaintenanceItem::all() as $item) {
            $date = $item->next_due_on->copy();
            while ($date->lte($to)) {
                if ($date->gte($from)) {
                    $events->push([
                        'date' => $date->toDateString(),
                        'type' => 'maintenance',
                        'kind' => $item->kind,
                        'title' => $item->title,
                        'id' => $item->id,
                        'projected' => ! $date->equalTo($item->next_due_on),
                    ]);
                }
                $date->addDays(max(1, $item->interval_days));
            }
        }

        Task::whereBetween('due_date', [$from, $to])->where('status', '!=', 'done')->get()
            ->each(fn (Task $t) => $events->push([
                'date' => $t->due_date->toDateString(), 'type' => 'task', 'title' => $t->title, 'id' => $t->id,
            ]));

        Trip::where('starts_at', '<=', $to)->where(fn ($q) => $q->where('ends_at', '>=', $from)->orWhereNull('ends_at'))->get()
            ->each(fn (Trip $t) => $events->push([
                'date' => $t->starts_at?->toDateString(), 'type' => 'trip', 'title' => $t->title, 'id' => $t->id,
            ]));

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'events' => $events->filter(fn ($e) => $e['date'])->sortBy('date')->values(),
        ]);
    }

    private function validated(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'title' => [$req, 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'kind' => ['sometimes', Rule::in(MaintenanceItem::KINDS)],
            'interval_days' => [$req, 'integer', 'min:1', 'max:3650'],
            'next_due_on' => [$req, 'date'],
            'remind_days_before' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'assignee_id' => ['nullable', Rule::exists('family_members', 'user_id')->where('family_id', $this->context->id())],
        ]);
    }
}

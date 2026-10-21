<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\ChecklistItem;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskLink;
use App\Models\Thread;
use App\Services\Notifier;
use App\Support\FamilyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(private FamilyContext $context, private Notifier $notifier) {}

    public function categories(): JsonResponse
    {
        return response()->json(TaskCategory::orderBy('position')->withCount(['tasks as open_count' => fn ($q) => $q->where('status', '!=', 'done')])->get());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'icon' => ['nullable', 'string', 'max:40'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        return response()->json(TaskCategory::create($data + ['position' => TaskCategory::max('position') + 1]), 201);
    }

    public function updateCategory(Request $request, TaskCategory $category): JsonResponse
    {
        $category->update($request->validate([
            'name' => ['sometimes', 'string', 'max:60'],
            'icon' => ['sometimes', 'string', 'max:40'],
            'color' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'position' => ['sometimes', 'integer'],
        ]));

        return response()->json($category);
    }

    public function destroyCategory(TaskCategory $category): JsonResponse
    {
        $category->delete();

        return response()->json(['ok' => true]);
    }

    public function index(Request $request): JsonResponse
    {
        $tasks = Task::query()
            ->with(['assignee:id,name,avatar_color', 'category:id,name,color,icon'])
            ->withCount(['checklist', 'checklist as checklist_done_count' => fn ($q) => $q->where('is_done', true), 'attachments'])
            ->when($request->integer('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->input('assignee') === 'me', fn ($q) => $q->where('assignee_id', $request->user()->id))
            ->when($request->input('q'), fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when(! $request->boolean('include_old_done'), fn ($q) => $q->where(
                fn ($q) => $q->where('status', '!=', 'done')->orWhere('completed_at', '>=', now()->subDays(30))
            ))
            ->orderBy('position')
            ->orderByDesc('id')
            ->get();

        return response()->json($tasks);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, true);

        $task = Task::create($data + [
            'created_by' => $request->user()->id,
            'position' => (Task::where('status', $data['status'] ?? 'todo')->min('position') ?? 1) - 1,
        ]);

        foreach ($request->input('checklist', []) as $i => $text) {
            if (trim((string) $text) !== '') {
                $task->checklist()->create(['text' => $text, 'position' => $i]);
            }
        }

        $this->notifyAssignee($task, $request);

        return response()->json($this->load($task), 201);
    }

    public function show(Task $task): JsonResponse
    {
        return response()->json($this->load($task));
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $data = $this->validated($request, false);
        $oldAssignee = $task->assignee_id;
        $oldStatus = $task->status;

        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            $data['completed_at'] = $data['status'] === 'done' ? now() : null;
        }

        $task->update($data);

        if ($task->assignee_id && $task->assignee_id !== $oldAssignee) {
            $this->notifyAssignee($task, $request);
        }

        if ($oldStatus !== 'done' && $task->status === 'done') {
            $this->notifier->send($task->family_id, 'projects', "Готово: {$task->title}", "Отметил(а) {$request->user()->name}", "/tasks/{$task->id}", $request->user());
        }

        return response()->json($this->load($task));
    }

    /** Drag & drop on the board: new status and the ordered ids of that column. */
    public function move(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Task::STATUSES)],
            'order' => ['array'],
            'order.*' => ['integer'],
        ]);

        $request->merge(['status' => $data['status']]);
        $this->update($request, $task);

        foreach ($data['order'] ?? [] as $position => $id) {
            Task::whereKey($id)->update(['position' => $position]);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->attachments->each->delete();
        $task->delete();

        return response()->json(['ok' => true]);
    }

    // --- Checklist ------------------------------------------------------------

    public function addChecklistItem(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:255']]);

        return response()->json($task->checklist()->create($data + ['position' => $task->checklist()->max('position') + 1]), 201);
    }

    public function updateChecklistItem(Request $request, Task $task, ChecklistItem $item): JsonResponse
    {
        abort_unless($item->task_id === $task->id, 404);
        $item->update($request->validate([
            'text' => ['sometimes', 'string', 'max:255'],
            'is_done' => ['sometimes', 'boolean'],
        ]));

        return response()->json($item);
    }

    public function deleteChecklistItem(Task $task, ChecklistItem $item): JsonResponse
    {
        abort_unless($item->task_id === $task->id, 404);
        $item->delete();

        return response()->json(['ok' => true]);
    }

    // --- Shop links -----------------------------------------------------------

    public function addLink(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:2048'],
            'title' => ['nullable', 'string', 'max:200'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json($task->links()->create($data), 201);
    }

    public function deleteLink(Task $task, TaskLink $link): JsonResponse
    {
        abort_unless($link->task_id === $task->id, 404);
        $link->delete();

        return response()->json(['ok' => true]);
    }

    // --- Before / after photos ------------------------------------------------

    public function upload(Request $request, Task $task): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:'.config('familyhub.max_upload_kb'), 'mimes:jpg,jpeg,png,webp,heic,heif,gif,pdf'],
            'kind' => ['required', Rule::in(['before', 'after', 'other'])],
            // thumbs[i] is the browser-made preview of files[i]
            'thumbs' => ['sometimes', 'array'],
            'thumbs.*' => ['file', 'max:1024', 'mimes:jpg,jpeg,webp'],
        ]);

        $thumbs = $request->file('thumbs', []);
        $created = collect($request->file('files'))
            ->map(fn ($file, $i) => Attachment::store($task, $file, $request->input('kind'), $request->user()->id, $thumbs[$i] ?? null));

        return response()->json($created, 201);
    }

    /** Opens (or creates) the discussion thread linked to this task. */
    public function thread(Request $request, Task $task): JsonResponse
    {
        $thread = Thread::firstOrCreate(
            ['task_id' => $task->id],
            ['title' => $task->title, 'icon' => 'clipboard-list', 'created_by' => $request->user()->id],
        );

        return response()->json($thread);
    }

    private function validated(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $familyId = $this->context->id();

        return $request->validate([
            'title' => [$req, 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::in(Task::STATUSES)],
            'priority' => ['sometimes', Rule::in(Task::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
            'category_id' => ['nullable', Rule::exists('task_categories', 'id')->where('family_id', $familyId)],
            'assignee_id' => ['nullable', Rule::exists('family_members', 'user_id')->where('family_id', $familyId)],
        ]);
    }

    private function load(Task $task): Task
    {
        return $task->load([
            'assignee:id,name,avatar_color', 'creator:id,name', 'category',
            'checklist', 'links', 'attachments.uploader:id,name', 'thread:id,task_id',
        ]);
    }

    private function notifyAssignee(Task $task, Request $request): void
    {
        if ($task->assignee) {
            $this->notifier->send($task->family_id, 'projects', "Вам назначена задача: {$task->title}", "От {$request->user()->name}", "/tasks/{$task->id}", $request->user(), [$task->assignee]);
        }
    }
}

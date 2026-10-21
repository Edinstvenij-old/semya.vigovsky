<?php

namespace App\Http\Controllers\Api;

use App\Enums\CheckinType;
use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\SafetyCheck;
use App\Models\SafetyResponse;
use App\Services\Checkins;
use App\Services\Notifier;
use App\Support\FamilyContext;
use App\Support\Live;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Is everyone OK?": one tap asks the whole family (urgent notification),
 * everyone answers "safe" / "in shelter" / "need help", and the dashboard
 * shows who has not answered yet.
 */
class SafetyController extends Controller
{
    private const CHECKIN_FOR = [
        'safe' => CheckinType::Safe,
        'shelter' => CheckinType::Shelter,
        'help' => CheckinType::NeedHelp,
    ];

    public function __construct(private FamilyContext $context, private Notifier $notifier, private Checkins $checkins) {}

    public function active(): JsonResponse
    {
        $check = SafetyCheck::open()->latest('id')->first();

        return response()->json(['check' => $check ? $this->present($check) : null]);
    }

    public function store(Request $request): JsonResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:200']])['note'] ?? null;

        // Only one open roll call at a time: asking again returns the current one.
        if ($open = SafetyCheck::open()->latest('id')->first()) {
            return response()->json(['check' => $this->present($open)]);
        }

        $user = $request->user();
        $check = SafetyCheck::create(['created_by' => $user->id, 'note' => $note]);
        // Whoever asks is evidently able to; mark them safe right away.
        $check->responses()->create(['user_id' => $user->id, 'status' => 'safe']);

        $this->notifier->send(
            $check->family_id, 'logistics', "{$user->name} спрашивает: все ли в порядке?",
            $note ?: 'Ответьте одной кнопкой на главной странице.', '/', $user,
        );

        Live::emit($check->family_id, 'safety');

        return response()->json(['check' => $this->present($check)], 201);
    }

    /** Answering is allowed for viewers too: grandparents must be able to say they are safe. */
    public function respond(Request $request, SafetyCheck $check): JsonResponse
    {
        abort_unless($check->closed_at === null, 422, 'Этот опрос уже закрыт.');

        $data = $request->validate([
            'status' => ['required', Rule::in(SafetyResponse::STATUSES)],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $check->responses()->updateOrCreate(['user_id' => $request->user()->id], $data);
        $this->checkins->record($request->user(), self::CHECKIN_FOR[$data['status']], ['note' => $data['note'] ?? null]);
        Live::emit($check->family_id, 'safety');

        return response()->json(['check' => $this->present($check->fresh())]);
    }

    public function close(Request $request, SafetyCheck $check): JsonResponse
    {
        abort_unless($check->created_by === $request->user()->id || $this->context->isAdmin(), 403);
        $check->update(['closed_at' => now()]);
        Live::emit($check->family_id, 'safety');

        return response()->json(['ok' => true]);
    }

    private function present(SafetyCheck $check): array
    {
        $responses = $check->responses()->get()->keyBy('user_id');
        $members = FamilyMember::with('user:id,name,avatar_color')
            ->where('family_id', $check->family_id)
            ->get()
            ->map(fn (FamilyMember $m) => [
                'user' => $m->user,
                'status' => $responses[$m->user_id]->status ?? null,
                'note' => $responses[$m->user_id]->note ?? null,
                'answered_at' => $responses[$m->user_id]->updated_at ?? null,
            ])
            // Need help first, then not answered, then the rest.
            ->sortBy(fn ($m) => match ($m['status']) {
                'help' => 0, null => 1, 'shelter' => 2, default => 3
            })
            ->values();

        return [
            'id' => $check->id,
            'note' => $check->note,
            'created_at' => $check->created_at,
            'creator' => $check->creator?->only(['id', 'name']),
            'members' => $members,
            'answered' => $members->whereNotNull('status')->count(),
            'total' => $members->count(),
        ];
    }
}

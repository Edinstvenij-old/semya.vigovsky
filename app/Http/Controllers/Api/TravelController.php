<?php

namespace App\Http\Controllers\Api;

use App\Enums\CheckinType;
use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\FamilyMember;
use App\Models\Trip;
use App\Services\Checkins;
use App\Support\FamilyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TravelController extends Controller
{
    public function __construct(private FamilyContext $context, private Checkins $checkins) {}

    public function checkinTypes(): JsonResponse
    {
        return response()->json(CheckinType::options());
    }

    /** Latest status of each family member plus the recent feed. */
    public function checkins(Request $request): JsonResponse
    {
        $feed = Checkin::with(['user:id,name,avatar_color', 'trip:id,title'])
            ->when($request->integer('trip_id'), fn ($q, $id) => $q->where('trip_id', $id))
            ->latest()
            ->limit(min(200, $request->integer('limit', 50)))
            ->get();

        $userIds = FamilyMember::where('family_id', $this->context->id())->pluck('user_id');
        $latest = Checkin::with('user:id,name,avatar_color')
            ->whereIn('id', Checkin::selectRaw('max(id)')->whereIn('user_id', $userIds)->groupBy('user_id'))
            ->latest()
            ->get();

        return response()->json(['latest' => $latest, 'feed' => $feed]);
    }

    public function checkin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(CheckinType::class)],
            'note' => ['nullable', 'string', 'max:200', Rule::requiredIf($request->input('type') === CheckinType::Custom->value)],
            'trip_id' => ['nullable', Rule::exists('trips', 'id')->where('family_id', $this->context->id())],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        // Logistics statuses are urgent: they arrive with sound.
        $checkin = $this->checkins->record($request->user(), CheckinType::from($data['type']), collect($data)->except('type')->all());

        return response()->json($checkin, 201);
    }

    public function destroyCheckin(Request $request, Checkin $checkin): JsonResponse
    {
        abort_unless($checkin->user_id === $request->user()->id || $this->context->isAdmin(), 403);
        $checkin->delete();

        return response()->json(['ok' => true]);
    }

    public function trips(): JsonResponse
    {
        return response()->json(
            Trip::with('travellers:id,name,avatar_color')
                ->withCount('documents')
                ->orderByRaw('case when ends_at is null or ends_at >= ? then 0 else 1 end', [now()])
                ->orderBy('starts_at')
                ->get()
        );
    }

    public function storeTrip(Request $request): JsonResponse
    {
        $data = $this->validatedTrip($request, true);
        $trip = Trip::create($data + ['created_by' => $request->user()->id]);
        $trip->travellers()->sync($request->input('traveller_ids', [$request->user()->id]));

        return response()->json($trip->load('travellers:id,name,avatar_color'), 201);
    }

    public function updateTrip(Request $request, Trip $trip): JsonResponse
    {
        $trip->update($this->validatedTrip($request, false));
        if ($request->has('traveller_ids')) {
            $trip->travellers()->sync($request->input('traveller_ids'));
        }

        return response()->json($trip->load('travellers:id,name,avatar_color'));
    }

    public function destroyTrip(Trip $trip): JsonResponse
    {
        $trip->delete();

        return response()->json(['ok' => true]);
    }

    private function validatedTrip(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:200'],
            'destination' => ['nullable', 'string', 'max:200'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'traveller_ids' => ['sometimes', 'array'],
            'traveller_ids.*' => [Rule::exists('family_members', 'user_id')->where('family_id', $this->context->id())],
        ]);
        unset($data['traveller_ids']);

        return $data;
    }
}

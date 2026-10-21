<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\TravelDocument;
use App\Support\FamilyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Shared folder of tickets & bookings. All routes are behind 2FA. */
class TravelDocumentController extends Controller
{
    public function __construct(private FamilyContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $q = $request->input('q');

        return response()->json(
            TravelDocument::with(['traveller:id,name,avatar_color', 'trip:id,title', 'attachments'])
                ->when($request->integer('trip_id'), fn ($query, $id) => $query->where('trip_id', $id))
                ->when($request->boolean('upcoming'), fn ($query) => $query->where(
                    fn ($w) => $w->where('departs_at', '>=', now()->subDay())->orWhereNull('departs_at')
                ))
                ->when($q, fn ($query) => $query->where(fn ($w) => $w
                    ->where('title', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%")
                    ->orWhere('booking_code', 'like', "%{$q}%")
                    ->orWhere('from_place', 'like', "%{$q}%")
                    ->orWhere('to_place', 'like', "%{$q}%")))
                ->orderByRaw('departs_at is null')
                ->orderBy('departs_at')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, true);
        $document = TravelDocument::create($data + ['uploaded_by' => $request->user()->id]);
        $this->storeFiles($request, $document);

        return response()->json($document->load(['traveller:id,name,avatar_color', 'trip:id,title', 'attachments']), 201);
    }

    public function update(Request $request, TravelDocument $document): JsonResponse
    {
        $document->update($this->validated($request, false));
        $this->storeFiles($request, $document);

        return response()->json($document->load(['traveller:id,name,avatar_color', 'trip:id,title', 'attachments']));
    }

    public function destroy(TravelDocument $document): JsonResponse
    {
        $document->attachments->each->delete();
        $document->delete();

        return response()->json(['ok' => true]);
    }

    private function storeFiles(Request $request, TravelDocument $document): void
    {
        $thumbs = $request->file('thumbs', []);
        foreach ($request->file('files', []) as $i => $file) {
            Attachment::store($document, $file, 'document', $request->user()->id, $thumbs[$i] ?? null);
        }
    }

    private function validated(Request $request, bool $creating): array
    {
        $familyId = $this->context->id();
        $data = $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:200'],
            'type' => ['sometimes', Rule::in(TravelDocument::TYPES)],
            'carrier' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:50'],
            'booking_code' => ['nullable', 'string', 'max:50'],
            'from_place' => ['nullable', 'string', 'max:120'],
            'to_place' => ['nullable', 'string', 'max:120'],
            'departs_at' => ['nullable', 'date'],
            'arrives_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'trip_id' => ['nullable', Rule::exists('trips', 'id')->where('family_id', $familyId)],
            'traveller_id' => ['nullable', Rule::exists('family_members', 'user_id')->where('family_id', $familyId)],
            'files' => ['sometimes', 'array', 'max:10'],
            'files.*' => ['file', 'max:'.config('familyhub.max_upload_kb'), 'mimes:pdf,jpg,jpeg,png,webp,heic,heif,pkpass'],
            'thumbs' => ['sometimes', 'array'],
            'thumbs.*' => ['file', 'max:1024', 'mimes:jpg,jpeg,webp'],
        ]);
        unset($data['files'], $data['thumbs']);

        return $data;
    }
}

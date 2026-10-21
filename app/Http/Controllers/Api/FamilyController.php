<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\Invitation;
use App\Services\AirAlerts;
use App\Services\FamilyBackup;
use App\Support\FamilyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FamilyController extends Controller
{
    public function __construct(private FamilyContext $context) {}

    /** Members list — visible to everyone in the family. */
    public function members(): JsonResponse
    {
        $members = FamilyMember::with('user:id,name,email,avatar_color')
            ->where('family_id', $this->context->id())
            ->get()
            ->map(fn (FamilyMember $m) => [
                'id' => $m->user->id,
                'name' => $m->user->name,
                'email' => $m->user->email,
                'avatar_color' => $m->user->avatar_color,
                'role' => $m->role->value,
                'role_label' => $m->role->label(),
                'relation' => $m->relation,
            ]);

        return response()->json([
            'family' => $this->context->family()->only(['id', 'name', 'backup_frequency', 'backup_keep', 'last_backup_at', 'alert_regions']),
            'members' => $members,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'backup_frequency' => ['sometimes', Rule::in(['off', 'daily', 'weekly'])],
            'backup_keep' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'alert_regions' => ['sometimes', 'array', 'max:10'],
            'alert_regions.*' => [Rule::in(AirAlerts::REGIONS)],
        ]);

        $family = $this->context->family();
        $family->update($data);

        return response()->json(['family' => $family->only(['id', 'name', 'backup_frequency', 'backup_keep', 'last_backup_at', 'alert_regions'])]);
    }

    public function updateMember(Request $request, int $userId): JsonResponse
    {
        $data = $request->validate([
            'role' => ['sometimes', Rule::enum(Role::class)],
            'relation' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $member = $this->member($userId);

        if (isset($data['role']) && $data['role'] !== Role::Admin->value && $member->role === Role::Admin) {
            $this->ensureAnotherAdmin($member);
        }

        $member->update($data);

        return response()->json(['ok' => true]);
    }

    public function removeMember(int $userId): JsonResponse
    {
        $member = $this->member($userId);
        if ($member->role === Role::Admin) {
            $this->ensureAnotherAdmin($member);
        }
        $member->delete();

        return response()->json(['ok' => true]);
    }

    public function invitations(): JsonResponse
    {
        return response()->json(
            Invitation::with('creator:id,name')->latest()->limit(50)->get()->map(fn (Invitation $i) => [
                'id' => $i->id,
                'role' => $i->role->value,
                'role_label' => $i->role->label(),
                'note' => $i->note,
                'uses' => $i->uses,
                'max_uses' => $i->max_uses,
                'expires_at' => $i->expires_at,
                'revoked_at' => $i->revoked_at,
                'valid' => $i->isValid(),
                'created_by' => $i->creator?->name,
            ])
        );
    }

    /** The raw link is returned only in this response; the database keeps a hash. */
    public function createInvitation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::enum(Role::class)],
            'note' => ['nullable', 'string', 'max:100'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:20'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ]);

        [$invitation, $token] = Invitation::issue([
            'created_by' => $request->user()->id,
            'role' => $data['role'],
            'note' => $data['note'] ?? null,
            'max_uses' => $data['max_uses'] ?? 1,
            'expires_at' => now()->addHours($data['hours'] ?? config('familyhub.invitation_ttl_hours')),
        ]);

        return response()->json([
            'id' => $invitation->id,
            'url' => url("/invite/{$token}"),
            'expires_at' => $invitation->expires_at,
        ], 201);
    }

    public function revokeInvitation(Invitation $invitation): JsonResponse
    {
        $invitation->update(['revoked_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function backups(FamilyBackup $backup): JsonResponse
    {
        return response()->json($backup->list($this->context->family()));
    }

    public function runBackup(FamilyBackup $backup): JsonResponse
    {
        $backup->run($this->context->family());

        return response()->json($backup->list($this->context->family()), 201);
    }

    public function downloadBackup(FamilyBackup $backup, string $name): StreamedResponse
    {
        abort_unless(preg_match('/^backup-[\d_-]+\.zip$/', $name), 404);
        $path = $backup->directory($this->context->family()).'/'.$name;
        $disk = Storage::disk(config('familyhub.backup_disk'));
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, $name);
    }

    private function member(int $userId): FamilyMember
    {
        return FamilyMember::where('family_id', $this->context->id())->where('user_id', $userId)->firstOrFail();
    }

    private function ensureAnotherAdmin(FamilyMember $member): void
    {
        $admins = FamilyMember::where('family_id', $member->family_id)->where('role', Role::Admin)->count();
        abort_if($admins <= 1, 422, __('В семье должен остаться хотя бы один администратор.'));
    }
}

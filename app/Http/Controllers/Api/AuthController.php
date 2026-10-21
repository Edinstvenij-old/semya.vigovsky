<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Notifier;
use App\Services\TwoFactor;
use App\Support\Msg;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function config(): JsonResponse
    {
        return response()->json([
            'app_name' => config('app.name'),
            'registration_open' => $this->registrationOpen(),
            'vapid_public_key' => config('familyhub.vapid.public_key'),
            'realtime' => config('broadcasting.default') === 'reverb' && config('broadcasting.connections.reverb.key') ? [
                'key' => config('broadcasting.connections.reverb.key'),
            ] + config('familyhub.realtime') : null,
            'flight_status_enabled' => (bool) config('familyhub.flights.api_key'),
            'telegram_enabled' => (bool) (config('familyhub.telegram.token') && config('familyhub.telegram.username')),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()?->toSessionArray()]);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember', true))) {
            throw ValidationException::withMessages(['email' => __('Неверный email или пароль.')]);
        }

        $request->session()->regenerate();

        return $this->me($request);
    }

    public function logout(Request $request, TwoFactor $twoFactor): JsonResponse
    {
        $twoFactor->forgetSession($request);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    /** Creates a new family with the registering user as its administrator. */
    public function register(Request $request): JsonResponse
    {
        abort_unless($this->registrationOpen(), 403, __('Регистрация закрыта. Попросите приглашение у администратора семьи.'));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)],
            'family_name' => ['required', 'string', 'max:100'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);
            $family = Family::create(['name' => $data['family_name'], 'created_by' => $user->id]);
            FamilyMember::create(['family_id' => $family->id, 'user_id' => $user->id, 'role' => Role::Admin]);
            $user->update(['current_family_id' => $family->id]);
            $family->seedDefaults();

            return $user;
        });

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        return $this->me($request);
    }

    public function showInvitation(string $token): JsonResponse
    {
        $invitation = Invitation::findValidByToken($token);
        abort_unless($invitation, 404, __('Приглашение недействительно или истекло.'));

        return response()->json([
            'family' => $invitation->family->name,
            'role' => $invitation->role->value,
            'role_label' => $invitation->role->label(),
            'invited_by' => $invitation->creator->name,
            'expires_at' => $invitation->expires_at,
        ]);
    }

    /** Accepts an invitation: either joins as the signed-in user or creates a new account. */
    public function acceptInvitation(Request $request, string $token, Notifier $notifier): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:190', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(10)],
            ], [
                'email.unique' => __('Этот email уже зарегистрирован — войдите и откройте ссылку снова.'),
            ]);
        }

        $relation = $request->validate(['relation' => ['nullable', 'string', 'max:50']])['relation'] ?? null;

        $user = DB::transaction(function () use ($token, $user, $relation, &$data) {
            $invitation = Invitation::withoutGlobalScopes()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            abort_unless($invitation?->isValid(), 404, __('Приглашение недействительно или истекло.'));

            $user ??= User::create($data);

            FamilyMember::firstOrCreate(
                ['family_id' => $invitation->family_id, 'user_id' => $user->id],
                ['role' => $invitation->role, 'relation' => $relation],
            );

            $invitation->increment('uses');
            $user->update(['current_family_id' => $invitation->family_id]);

            return $user;
        });

        if (! $request->user()) {
            Auth::guard('web')->login($user, true);
            $request->session()->regenerate();
        }

        $notifier->send($user->current_family_id, 'family', Msg::t(':name присоединился(ась) к семье', ['name' => $user->name]), null, '/family', $user);

        return response()->json(['user' => $user->fresh()->toSessionArray()]);
    }

    private function registrationOpen(): bool
    {
        return (bool) config('familyhub.allow_registration') || ! Family::exists();
    }
}

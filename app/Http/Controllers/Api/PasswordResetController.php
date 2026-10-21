<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\User;
use App\Support\FamilyContext;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    /**
     * Emails a reset link. Always answers the same way, so the form cannot be
     * used to find out which emails are registered.
     */
    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => __('Если такой email есть в семье, на него придёт письмо со ссылкой. Не пришло — попросите администратора семьи создать ссылку для сброса.'),
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('Ссылка недействительна или устарела. Запросите новую.')]);
        }

        return response()->json(['message' => __('Пароль изменён. Теперь можно войти.')]);
    }

    /**
     * Admin fallback for relatives who do not read email: a one-time reset link
     * the admin hands over in person. Only for people who belong to this family
     * alone, so an admin can never take over someone else's account elsewhere.
     */
    public function adminLink(FamilyContext $context, int $userId): JsonResponse
    {
        $member = FamilyMember::with('user')
            ->where('family_id', $context->id())
            ->where('user_id', $userId)
            ->firstOrFail();

        abort_if(
            FamilyMember::where('user_id', $userId)->where('family_id', '!=', $context->id())->exists(),
            403,
            __('Этот человек состоит и в другой семье — сбросить пароль он может только сам, через «Забыли пароль?».'),
        );

        $token = Password::createToken($member->user);

        return response()->json([
            'url' => url('/reset-password/'.$token.'?email='.urlencode($member->user->email)),
            'expires_in_minutes' => config('auth.passwords.users.expire'),
        ]);
    }
}

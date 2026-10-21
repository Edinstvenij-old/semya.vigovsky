<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TwoFactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(private TwoFactor $twoFactor) {}

    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'enabled' => $request->user()->hasTwoFactor(),
            'verified' => $this->twoFactor->sessionVerified($request),
            'recovery_codes_left' => count($request->user()->two_factor_recovery_codes ?? []),
        ]);
    }

    /** Step 1: generate a secret and QR code. Not active until confirmed. */
    public function setup(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();

        abort_if($user->hasTwoFactor(), 409, __('2FA уже включена.'));

        $secret = $this->twoFactor->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null])->save();

        return response()->json([
            'secret' => $secret,
            'qr_svg' => $this->twoFactor->qrSvg($user, $secret),
        ]);
    }

    /** Step 2: confirm with the first code from the app; returns recovery codes once. */
    public function confirm(Request $request): JsonResponse
    {
        $code = $request->validate(['code' => ['required', 'string']])['code'];
        $user = $request->user();

        if (! $user->two_factor_secret || ! $this->twoFactor->verifyCode($user->two_factor_secret, $code)) {
            throw ValidationException::withMessages(['code' => __('Неверный код. Проверьте время на телефоне.')]);
        }

        $codes = $this->twoFactor->recoveryCodes();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes,
        ])->save();
        $this->twoFactor->markSessionVerified($request);

        return response()->json(['recovery_codes' => $codes]);
    }

    /** Unlocks the documents module for this session. */
    public function verify(Request $request): JsonResponse
    {
        $code = $request->validate(['code' => ['required', 'string']])['code'];
        $user = $request->user();

        if (! $user->hasTwoFactor() || ! $this->twoFactor->verifyUser($user, $code)) {
            throw ValidationException::withMessages(['code' => __('Неверный код.')]);
        }

        $this->twoFactor->markSessionVerified($request);

        return response()->json(['verified' => true]);
    }

    public function disable(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
        $this->twoFactor->forgetSession($request);

        return response()->json(['enabled' => false]);
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\TwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the documents module: the user must have 2FA enabled and must
 * have entered a code in this session recently.
 */
class RequireTwoFactor
{
    public function __construct(private TwoFactor $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->hasTwoFactor()) {
            return response()->json([
                'message' => 'Для доступа к документам включите двухфакторную аутентификацию.',
                'two_factor' => 'setup_required',
            ], 423);
        }

        if (! $this->twoFactor->sessionVerified($request)) {
            return response()->json([
                'message' => 'Введите код из приложения-аутентификатора.',
                'two_factor' => 'challenge_required',
            ], 423);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\FamilyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the signed-in user's current family. Runs before route model
 * binding, so models from other families resolve to 404.
 */
class SetFamilyContext
{
    public function __construct(private FamilyContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $membership = $user?->membership();

        if (! $membership) {
            // Current family missing or membership revoked — fall back to any family the user is in.
            $first = $user?->families()->first();
            if ($first) {
                $user->update(['current_family_id' => $first->id]);
                $membership = $user->membership($first->id);
            }
        }

        if (! $membership) {
            return response()->json(['message' => 'Вы не состоите ни в одной семье.'], 403);
        }

        $this->context->set($membership->family, $membership);

        return $next($request);
    }
}

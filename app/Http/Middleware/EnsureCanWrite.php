<?php

namespace App\Http\Middleware;

use App\Support\FamilyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Viewers (observer role) get read-only access: every mutating request is rejected. */
class EnsureCanWrite
{
    public function __construct(private FamilyContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() && ! $this->context->role()?->canWrite()) {
            return response()->json(['message' => 'У наблюдателя доступ только для просмотра.'], 403);
        }

        return $next($request);
    }
}

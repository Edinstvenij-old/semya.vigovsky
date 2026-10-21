<?php

namespace App\Http\Middleware;

use App\Support\FamilyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFamilyAdmin
{
    public function __construct(private FamilyContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->context->isAdmin()) {
            return response()->json(['message' => 'Доступно только администратору семьи.'], 403);
        }

        return $next($request);
    }
}

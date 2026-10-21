<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Language of API answers (validation errors, messages): the user's choice,
 * otherwise what the app sends in X-Locale (device language), otherwise Russian.
 */
class SetLocale
{
    public const SUPPORTED = ['ru', 'uk'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->pref('locale') ?: $request->header('X-Locale');

        app()->setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : 'ru');

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the site out of search engines (X-Robots-Tag on every response)
 * and optionally puts HTTP Basic auth in front of everything.
 */
class PrivateSite
{
    public function handle(Request $request, Closure $next): Response
    {
        ['user' => $user, 'password' => $password] = config('familyhub.basic_auth');

        if ($user && $password && ! $request->is('up')) {
            $ok = hash_equals($user, (string) $request->getUser())
                && hash_equals($password, (string) $request->getPassword());

            if (! $ok) {
                return response('Authentication required', 401, [
                    'WWW-Authenticate' => 'Basic realm="Family Hub", charset="UTF-8"',
                    'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                ]);
            }
        }

        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Sanctum recognises SPA requests by a same-origin Referer; other sites still get nothing.
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(self), camera=(self), microphone=()');

        return $response;
    }
}

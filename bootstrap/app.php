<?php

use App\Http\Middleware\EnsureCanWrite;
use App\Http\Middleware\EnsureFamilyAdmin;
use App\Http\Middleware\PrivateSite;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SetFamilyContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->prepend(PrivateSite::class);
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'family' => SetFamilyContext::class,
            'can-write' => EnsureCanWrite::class,
            'family-admin' => EnsureFamilyAdmin::class,
            'two-factor' => RequireTwoFactor::class,
        ]);

        // The family context must exist before route model binding, so that
        // models of other families are filtered out by the global scope (→ 404).
        $middleware->prependToPriorityList(SubstituteBindings::class, SetFamilyContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

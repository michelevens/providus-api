<?php

use App\Http\Middleware\EmbedCors;
use App\Http\Middleware\EnforcePlanLimits;
use App\Http\Middleware\EnsureAgencyRole;
use App\Http\Middleware\EnsureWriteAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => EnsureAgencyRole::class,
            'write' => EnsureWriteAccess::class,
            'embed.cors' => EmbedCors::class,
            'plan.limit' => EnforcePlanLimits::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

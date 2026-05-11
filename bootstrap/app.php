<?php

use App\Http\Middleware\EmbedCors;
use App\Http\Middleware\EnforcePlanLimits;
use App\Http\Middleware\EnsureAgencyRole;
use App\Http\Middleware\EnsureWriteAccess;
use App\Http\Middleware\PromoteSessionCookieToBearer;
use App\Http\Middleware\RequireCsrfTokenHeader;
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

        // Promote the HttpOnly `credentik_session` cookie into a Bearer
        // header on the way in so Sanctum's TokenGuard sees it normally.
        // Runs first in the api group; dual-mode safe — clients that
        // already send a Bearer header are untouched. Remove this
        // middleware once all V2 sessions have migrated to cookies
        // (~1-2 weeks after rollout).
        $middleware->prependToGroup('api', PromoteSessionCookieToBearer::class);

        // CSRF guard: requires X-Requested-With on cookie-auth POST/PUT/
        // PATCH/DELETE. Prepended (not appended) so it runs BEFORE
        // route-level auth:sanctum — otherwise a bad cookie that fails
        // auth gets 401'd before we can return the more-specific 403.
        // Bearer-only callers and GET/HEAD/OPTIONS bypass internally.
        $middleware->prependToGroup('api', RequireCsrfTokenHeader::class);

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

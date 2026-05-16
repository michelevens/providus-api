<?php

use App\Http\Middleware\EmbedCors;
use App\Http\Middleware\EnforcePlanLimits;
use App\Http\Middleware\EnsureAgencyRole;
use App\Http\Middleware\EnsureWriteAccess;
use App\Http\Middleware\PromoteSessionCookieToBearer;
use App\Http\Middleware\RequireCsrfTokenHeader;
use Illuminate\Auth\AuthenticationException;
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

        // This is a JSON API; there is no /login route. When a guest
        // hits an auth:sanctum endpoint with Accept: text/html (e.g.
        // someone clicks an <a href> to /rcm/denials/{id}/pdf or
        // /docx that opens in a new tab — those are navigation
        // requests, not XHR), Laravel's default Authenticate
        // middleware tries to redirect to route('login') and 500s
        // with "Route [login] not defined."
        //
        // Returning null tells the middleware to abort with the
        // standard 401 JSON response regardless of Accept header.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This is a JSON API; there is no /login route. By default
        // Laravel's exception handler renders an AuthenticationException
        // (any guard rejection) by calling redirect()->guest(route('login'))
        // unless the request "expects JSON" — which a top-level browser
        // navigation does NOT, even when Accept: text/html includes
        // /json after it. Effect: clicking the V2 <a href="/api/.../pdf"
        // or /docx Download buttons in a new tab while guest/expired
        // surfaces "Route [login] not defined" 500.
        //
        // Force a 401 JSON response for every AuthenticationException
        // raised under the /api prefix, regardless of Accept header.
        // Non-/api paths (there essentially are none on this app, but
        // /up health check etc.) keep Laravel's default behavior.
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Unauthenticated.',
                ], 401);
            }
            return null; // fall through to default handler
        });
    })->create();

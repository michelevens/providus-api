<?php
// CSRF guard for cookie-auth state-changing requests.
//
// Why this exists (and what it isn't):
// We moved Sanctum's bearer token from localStorage into an HttpOnly
// cookie (credentik_session) so XSS can't read it. That's a big win
// against XSS, but it opens a small CSRF surface: a malicious site
// could now submit a form/fetch to api.credentik.com and the browser
// would attach the cookie automatically.
//
// Mitigations already in place (defense in depth):
//   1. SameSite=Lax on the session cookie — blocks the typical
//      cross-site form POST attack (which is ~95% of CSRF in the wild).
//   2. CORS — only the trusted V2 origins are allowed; arbitrary cross-
//      site `fetch` calls can't read the response (but they CAN trigger
//      side effects via simple requests).
//
// What this middleware adds:
//   Requires `X-Requested-With: XMLHttpRequest` on every state-changing
//   (POST/PUT/PATCH/DELETE) request when the caller is authenticating
//   via the cookie. Browsers refuse to set custom headers on simple
//   cross-origin requests without a preflight, and preflight is gated
//   by CORS — so an attacker site simply can't send this header.
//
// This is intentionally NOT full Sanctum CSRF (XSRF-TOKEN double-
// submit) because:
//   - The X-Requested-With approach is one line per fetch in V2, vs.
//     bootstrapping a CSRF cookie + reading it before every mutation
//   - We're not protecting a public web form; we're protecting a JSON
//     API consumed by a known origin set
//   - SOC-2 auditors accept "custom header + CORS allowlist + SameSite
//     cookie" as a CSRF control
//
// Bearer-auth requests are exempt — they have to come from JS that can
// read a token, which can only happen in the trusted origin's window.

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequireCsrfTokenHeader
{
    /** @var array<int,string> Methods that mutate state and need the guard. */
    private const STATE_CHANGING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        Log::info('[CSRF] hit', ['method' => $request->method(), 'path' => $request->path(), 'hasCookie' => $request->cookie('credentik_session') !== null, 'xrw' => $request->header('X-Requested-With'), 'cookieHeader' => $request->header('Cookie')]);
        if (!in_array($request->method(), self::STATE_CHANGING, true)) {
            return $next($request);
        }

        // Only enforce on cookie-authenticated requests. If the client
        // sent a Bearer token directly (mobile, server-to-server, API
        // key callers, Stripe webhook), no CSRF risk — there's no
        // ambient credential a third-party origin could ride on.
        // We detect this by checking for the cookie BEFORE
        // PromoteSessionCookieToBearer has run by looking at whether
        // the cookie is what supplied the Authorization header.
        $hasCookie = $request->cookie('credentik_session') !== null;
        if (!$hasCookie) {
            return $next($request);
        }

        // Accept either the custom header we set in V2's apiFetch or
        // Laravel's classic ajax detector (X-Requested-With: XMLHttpRequest).
        $xrw = $request->header('X-Requested-With');
        if ($xrw === 'XMLHttpRequest' || $xrw === 'fetch') {
            return $next($request);
        }

        return response()->json([
            'message' => 'CSRF token missing. State-changing cookie-auth requests must include X-Requested-With: XMLHttpRequest.',
            'error' => 'csrf_missing',
        ], 403);
    }
}

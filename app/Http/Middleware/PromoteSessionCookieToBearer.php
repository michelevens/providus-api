<?php
// Auth migration: dual-mode bearer + HttpOnly cookie.
//
// V2 was storing the Sanctum bearer token in localStorage, which means any
// XSS lands access to the full session. The fix is to issue the same token
// as an HttpOnly cookie that JavaScript can't read. We don't want to
// rewrite the entire Sanctum guard chain to read from cookies, though, so
// this middleware does the minimum: if the request lacks an Authorization
// header but carries the `credentik_session` cookie, copy the cookie value
// into the Authorization header before Sanctum's TokenGuard sees it.
//
// This keeps the dual-mode transition safe: clients still sending the
// bearer header keep working (back-compat), and the new cookie path uses
// the same Sanctum machinery without changes to controllers, scopes, or
// policies.
//
// Removed once V2 has been on cookies long enough that no live bearer-
// based session remains. Plan: 1-2 weeks of dual-mode then drop this file.

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PromoteSessionCookieToBearer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->hasHeader('Authorization')) {
            $cookieToken = $request->cookie('credentik_session');
            if ($cookieToken) {
                $request->headers->set('Authorization', 'Bearer ' . $cookieToken);
            }
        }

        return $next($request);
    }
}

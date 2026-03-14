<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgencyRole
{
    /**
     * Handle an incoming request.
     *
     * Accepts one or more role names. The user passes if:
     *   - They are a superadmin (always passes), OR
     *   - Their role is in the allowed list.
     *
     * When no roles are passed the middleware just checks authentication.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // SuperAdmin has access to everything
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // If specific roles were listed, the user must match one of them
        if (!empty($roles) && !in_array($user->role, $roles)) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        return $next($request);
    }
}

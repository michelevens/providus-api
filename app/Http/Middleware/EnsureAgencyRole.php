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

        // If specific roles were listed, the user must have at least the
        // highest required role level (using the hierarchy, not exact match).
        if (!empty($roles)) {
            $minLevel = min(array_map(
                fn ($r) => User::ROLE_HIERARCHY[$r] ?? 99,
                $roles
            ));
            $userLevel = User::ROLE_HIERARCHY[$user->role] ?? 0;

            if ($userLevel < $minLevel) {
                return response()->json(['error' => 'Insufficient permissions'], 403);
            }
        }

        return $next($request);
    }
}

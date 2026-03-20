<?php

namespace App\Http\Middleware;

use App\Models\Agency;
use Closure;
use Illuminate\Http\Request;

class EnforcePlanLimits
{
    /**
     * Check plan limits before creating providers, users, or applications.
     * Usage in routes: ->middleware('plan.limit:providers') or plan.limit:users or plan.limit:applications
     */
    public function handle(Request $request, Closure $next, string $resource): mixed
    {
        // Only enforce on POST (create) requests
        if (!$request->isMethod('POST')) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user || !$user->agency_id) {
            return $next($request);
        }

        $agency = Agency::find($user->agency_id);
        if (!$agency) {
            return $next($request);
        }

        // Superadmins bypass limits
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Check if agency subscription is active
        if ($agency->hasExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription has expired. Please renew to continue.',
                'error_code' => 'subscription_expired',
            ], 403);
        }

        $limit = $agency->planLimit($resource);

        // -1 = unlimited
        if ($limit === -1) {
            return $next($request);
        }

        // 0 = not defined (unknown plan)
        if ($limit === 0) {
            return $next($request);
        }

        $currentCount = match ($resource) {
            'providers' => $agency->providers()->count(),
            'users' => $agency->users()->count(),
            'applications' => $agency->applications()->count(),
            default => 0,
        };

        if ($currentCount >= $limit) {
            $planName = ucfirst($agency->plan_tier ?? 'starter');
            return response()->json([
                'success' => false,
                'message' => "You've reached the {$resource} limit ({$limit}) for your {$planName} plan. Please upgrade to add more.",
                'error_code' => 'plan_limit_reached',
                'limit' => $limit,
                'current' => $currentCount,
                'plan' => $agency->plan_tier,
            ], 403);
        }

        return $next($request);
    }
}

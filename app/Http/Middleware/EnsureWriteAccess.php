<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWriteAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === 'readonly') {
            return response()->json(['error' => 'Read-only access. Contact your admin for write permissions.'], 403);
        }

        return $next($request);
    }
}

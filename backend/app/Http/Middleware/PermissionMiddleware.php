<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $permissionsArray = explode('|', $permissions);

        foreach ($permissionsArray as $permission) {
            if ($request->user()->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Unauthorized.'], 403);
    }
}

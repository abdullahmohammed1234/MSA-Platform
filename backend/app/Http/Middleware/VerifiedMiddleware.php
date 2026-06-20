<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifiedMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($request->user() instanceof MustVerifyEmail && !$request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Your email address is not verified.'], 403);
        }

        return $next($request);
    }
}

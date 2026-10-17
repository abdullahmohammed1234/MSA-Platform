<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as BaseAuthenticate;
use Illuminate\Http\JsonResponse;

/**
 * Replaces the framework `auth` alias so every `auth:*` route also rejects
 * deactivated accounts, regardless of whether the user arrived via a
 * bearer token or a stateful session.
 */
class Authenticate extends BaseAuthenticate
{
    public const DEACTIVATED_MESSAGE = 'Your account has been deactivated. Please contact support.';

    public function handle($request, Closure $next, ...$guards)
    {
        $this->authenticate($request, $guards);

        $user = $request->user();

        if ($user !== null && isset($user->is_active) && ! $user->is_active) {
            return new JsonResponse(['message' => self::DEACTIVATED_MESSAGE], 401);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\ApplicationAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplicationAccessMiddleware
{
    protected $appAccessService;

    public function __construct(ApplicationAccessService $appAccessService)
    {
        $this->appAccessService = $appAccessService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $application): Response
    {
        $user = $request->user();

        if (!$user) {
            throw new \Illuminate\Auth\AuthenticationException('Unauthenticated.');
        }

        if (!$this->appAccessService->canAccess($user, $application)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Forbidden: You do not have access to this application.');
        }

        return $next($request);
    }
}

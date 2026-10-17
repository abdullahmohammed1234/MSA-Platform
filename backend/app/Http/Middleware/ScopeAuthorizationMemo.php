<?php

namespace App\Http\Middleware;

use App\Support\AuthorizationMemo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScopeAuthorizationMemo
{
    public function __construct(private readonly AuthorizationMemo $memo) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->memo->begin();

        try {
            return $next($request);
        } finally {
            $this->memo->end();
        }
    }
}

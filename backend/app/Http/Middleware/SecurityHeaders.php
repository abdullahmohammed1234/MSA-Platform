<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip headers for binary file outputs (e.g., download responses)
        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            return $response;
        }

        // Set standard security headers
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');

        // Content Security Policy (CSP)
        // Allow scripts and styles from self, google fonts, and required resources.
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://apis.google.com; " .
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' https://fonts.gstatic.com; " .
               "connect-src 'self' ws: wss: http://localhost:8000 http://127.0.0.1:8000 " . env('APP_URL') . "; " .
               "frame-ancestors 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self';";
        
        $response->headers->set('Content-Security-Policy', $csp);

        // Enforce HSTS if secure (HTTPS) or explicitly forced via configuration
        if ($request->secure() || env('FORCE_HTTPS', false)) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}

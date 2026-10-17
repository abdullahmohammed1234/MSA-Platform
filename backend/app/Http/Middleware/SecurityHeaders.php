<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * JSON and other non-document payloads never need to load or run anything.
     */
    public const API_POLICY = "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'";

    /**
     * Server-rendered HTML (welcome page, error pages). Their styles live in
     * public/css; Bunny fonts are the only third-party origin.
     */
    public const HTML_POLICY = "default-src 'self'; script-src 'self'; style-src 'self' https://fonts.bunny.net; "
        ."font-src 'self' https://fonts.bunny.net; img-src 'self' data:; connect-src 'self'; object-src 'none'; "
        ."base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    /**
     * Laravel's debug exception page inlines its scripts. APP_DEBUG must be
     * false in production, so this never applies there.
     */
    public const DEBUG_HTML_POLICY = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; "
        ."font-src 'self' https://fonts.bunny.net; img-src 'self' data:; connect-src 'self'; object-src 'none'; "
        ."base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    /**
     * Inline PDFs are rendered by the browser's viewer; only framing is restricted.
     */
    public const PDF_POLICY = "frame-ancestors 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');

        if ($response instanceof BinaryFileResponse) {
            return $response;
        }

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $response->headers->set('Content-Security-Policy', $this->policyFor($response));

        if ($request->secure() || config('app.force_https', false)) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }

    private function policyFor(Response $response): string
    {
        // View responses do not receive a Content-Type until they are sent,
        // so an absent type is treated as a document.
        $type = strtolower((string) $response->headers->get('Content-Type', ''));

        if (str_contains($type, 'application/pdf')) {
            return self::PDF_POLICY;
        }

        if ($type === '' || str_contains($type, 'html') || str_contains($type, 'xml')) {
            return config('app.debug') ? self::DEBUG_HTML_POLICY : self::HTML_POLICY;
        }

        return self::API_POLICY;
    }
}

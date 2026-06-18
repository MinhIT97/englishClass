<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline security headers to every HTTP response.
 *
 * - X-Frame-Options: DENY  -> clickjacking defense
 * - X-Content-Type-Options: nosniff -> blocks MIME sniffing
 * - Referrer-Policy: same-origin -> limits referrer leakage
 * - Permissions-Policy -> disables powerful features we don't use
 * - Content-Security-Policy -> baseline (strict if your app allows,
 *   relaxed here to accommodate inline JS that Vite injects during dev)
 *
 * For production-grade CSP, audit every <script src>, <style>, and
 * inline event handler in your views and tighten this string. A strict
 * policy would be:
 *   default-src 'self'; script-src 'self'; style-src 'self' 'nonce-...';
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(self), camera=(self)');

        // Don't advertise server tech in the X-Powered-By header.
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        // Baseline CSP — strict but accommodates Vite-injected inline
        // styles in dev. In production, move to nonce-based and remove
        // 'unsafe-inline'.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: https:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "connect-src 'self' https://api.telegram.org wss: ws:",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
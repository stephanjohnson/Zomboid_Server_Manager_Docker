<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = bin2hex(random_bytes(16));
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $this->addSecurityHeaders($response, $nonce);

        return $response;
    }

    private function addSecurityHeaders(Response $response, string $nonce): void
    {
        $isLocal = app()->environment('local');

        $scriptSrc = "'self' 'nonce-{$nonce}'";
        $connectSrc = "'self'";
        $styleSrc = "'self' 'unsafe-inline' https://fonts.bunny.net";
        $fontSrc = "'self' https://fonts.bunny.net";

        if ($isLocal) {
            $scriptSrc .= " 'unsafe-eval'";
            $connectSrc .= ' ws://localhost:5173 http://localhost:5173';
        }

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src {$scriptSrc}",
            "style-src {$styleSrc}",
            "img-src 'self' data:",
            "connect-src {$connectSrc}",
            "font-src {$fontSrc}",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'", "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://s3.tradingview.com https://www.tradingview.com http://localhost:5173",
            "style-src 'self' 'unsafe-inline' https://www.tradingview.com", "img-src 'self' data: https://*.tradingview.com",
            "font-src 'self' data:", "frame-src https://*.tradingview.com",
            "connect-src 'self' https://*.tradingview.com wss://*.tradingview.com http://localhost:5173 ws://localhost:5173",
            "object-src 'none'", "base-uri 'self'", "form-action 'self'", "frame-ancestors 'self'",
        ]));
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}

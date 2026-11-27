<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança em todas as respostas.
 *
 * - Anti-clickjacking: nenhuma página pode ser embutida em outro site. A
 *   exceção é o iframe do widget, que já define o próprio CSP
 *   frame-ancestors com os domínios autorizados (WidgetFrameController).
 * - HSTS quando a requisição chega por HTTPS.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', "frame-ancestors 'none'");
            $headers->set('X-Frame-Options', 'DENY');
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}

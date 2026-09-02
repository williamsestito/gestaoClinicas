<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança aplicados no nível da aplicação (independente do
 * servidor web em produção — o nginx do docker-compose já reforça um
 * subconjunto destes, mas o deploy real (ex.: Laravel Cloud, ver
 * CLAUDE.md) pode usar um front diferente). HSTS e CSP são condicionados
 * para nunca quebrar o ambiente local (HTTP, sem certificado).
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if (app()->isProduction() && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        if (app()->isProduction()) {
            // Report-Only: nunca bloqueia a página, apenas registra
            // violações no console do navegador. Política restritiva
            // completa (enforced) fica documentada em
            // docs/architecture/security-baseline.md até ser validada
            // contra todos os assets reais (analytics, mapas, etc.), caso
            // sejam adicionados em fases futuras.
            $response->headers->set('Content-Security-Policy-Report-Only', implode('; ', [
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: https:",
                "font-src 'self' data:",
                "connect-src 'self'",
                "frame-ancestors 'self'",
                "base-uri 'self'",
                "form-action 'self'",
            ]));
        }

        return $response;
    }
}

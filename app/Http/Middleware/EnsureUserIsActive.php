<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia qualquer requisição autenticada de um usuário com is_active =
 * false (conta desativada/encerrada) — sessões antigas não continuam
 * funcionando após a desativação. Aplicado globalmente ao grupo "web": não
 * afeta visitantes anônimos.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Esta conta está desativada. Entre em contato com o suporte se precisar reativá-la.');
        }

        return $next($request);
    }
}

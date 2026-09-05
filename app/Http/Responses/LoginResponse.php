<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Substitui o LoginResponse padrão do Fortify para decidir o destino
 * pós-login sem passar por /dashboard quando o usuário é platform admin.
 *
 * Achado real (tela branca em produção): o formulário de login usa o
 * componente <Form> do Inertia, então o POST já chega com o header
 * X-Inertia. Um redirect() comum para /dashboard é seguido pelo próprio
 * cliente Inertia; se o middleware de tenancy precisar redirecionar de novo
 * dali para /admin (painel Filament, HTML puro), o cliente tenta processar
 * essa resposta como se fosse uma página Inertia — tela branca. Resolvendo
 * o destino aqui, o platform admin nunca entra nesse segundo salto: vai
 * direto para /admin com Inertia::location(), que devolve 409 +
 * X-Inertia-Location (o cliente faz um window.location real) quando a
 * requisição atual é uma visita Inertia, ou um redirect comum quando não é.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $user = $request->user('web');

        if ($user->is_platform_admin) {
            return Inertia::location('/admin');
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}

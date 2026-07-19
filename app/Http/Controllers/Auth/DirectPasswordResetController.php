<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmPasswordResetEmailRequest;
use App\Http\Requests\Auth\DirectPasswordResetRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Atalho de "esqueci a senha" exclusivo de ambiente local/testing: em vez
 * do link por e-mail (fluxo padrão do Fortify, mantido intacto em
 * produção), confirma que o e-mail existe e permite salvar a nova senha
 * diretamente, sem provar posse do e-mail. Nunca deve ficar acessível fora
 * de local/testing — daí o bloqueio no construtor, em duas camadas (rota +
 * aqui) como defesa em profundidade.
 */
class DirectPasswordResetController extends Controller
{
    public function __construct()
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
    }

    public function confirmEmail(ConfirmPasswordResetEmailRequest $request): RedirectResponse
    {
        $email = $request->validated('email');

        if (! User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Não encontramos nenhuma conta com este e-mail.',
            ]);
        }

        return back()->with('confirmedEmail', $email);
    }

    public function update(DirectPasswordResetRequest $request, ResetUserPassword $resetUserPassword): RedirectResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Não encontramos nenhuma conta com este e-mail.',
            ]);
        }

        $resetUserPassword->reset($user, $request->validated());

        return to_route('login')->with('status', 'Senha redefinida com sucesso. Faça login com sua nova senha.');
    }
}

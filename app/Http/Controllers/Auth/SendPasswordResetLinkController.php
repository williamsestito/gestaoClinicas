<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PatientUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * "Esqueci minha senha" em /login — mesmo racional de
 * App\Providers\FortifyServiceProvider::configureActions() (login
 * unificado): reconhece, pelo e-mail, se a conta é de staff (broker
 * "users") ou de paciente (broker "patient_users", config/auth.php) e
 * envia o link pelo canal certo. Sempre a mesma mensagem de sucesso,
 * exista ou não a conta — nunca revela em qual tabela (ou se em nenhuma)
 * o e-mail está cadastrado, mesma disciplina de
 * PatientPortal\PatientPasswordResetController::store().
 */
class SendPasswordResetLinkController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $email = (string) $request->input('email');

        if (User::query()->where('email', $email)->exists()) {
            Password::broker('users')->sendResetLink(['email' => $email]);
        } elseif (PatientUser::query()->where('email', $email)->exists()) {
            Password::broker('patient_users')->sendResetLink(['email' => $email]);
        }

        return back()->with('status', 'Se este e-mail estiver cadastrado, enviamos um link para redefinir a senha.');
    }
}

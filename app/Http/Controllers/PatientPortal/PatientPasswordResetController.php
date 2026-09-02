<?php

declare(strict_types=1);

namespace App\Http\Controllers\PatientPortal;

use App\Actions\PatientPortal\ResetPatientPasswordAction;
use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\PatientUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Esqueci minha senha" do portal do paciente — mesmo PasswordBroker do
 * Laravel usado pelo staff, mas contra o provider/tabela "patient_users"
 * (config/auth.php), nunca compartilhado com o broker de staff.
 */
class PatientPasswordResetController extends Controller
{
    use PasswordValidationRules;

    public function create(): Response
    {
        return Inertia::render('patient-portal/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::broker('patient_users')->sendResetLink($request->only('email'));

        // Sempre a mesma mensagem, exista ou não a conta — não revela se um
        // e-mail está cadastrado.
        return back()->with('status', 'Se este e-mail estiver cadastrado, enviamos um link para redefinir a senha.');
    }

    public function edit(Request $request): Response
    {
        return Inertia::render('patient-portal/ResetPassword', [
            'email' => $request->query('email'),
            'token' => $request->route('token'),
            'passwordRules' => PasswordRule::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(Request $request, ResetPatientPasswordAction $action): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => $this->passwordRules(),
        ]);

        $status = Password::broker('patient_users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (PatientUser $patientUser, string $password) use ($action) {
                $action->handle($patientUser, $password);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        return to_route('login')->with('status', 'Senha redefinida com sucesso. Faça login com a nova senha.');
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\PatientPortal;

use App\Models\PatientUser;
use Illuminate\Support\Str;

/**
 * Aplica a nova senha após o link de redefinição validado pelo
 * PasswordBroker "patient_users" (ver config/auth.php) — mesmo papel de
 * App\Actions\Fortify\ResetUserPassword, mas fora do contrato do Fortify
 * (o portal não usa Fortify, ver docs/modules/patient-portal.md).
 *
 * Rotaciona também o remember_token — sem isso, um cookie "lembrar de mim"
 * roubado antes da redefinição continuaria autenticando indefinidamente
 * mesmo depois da vítima trocar a senha (achado de security-review desta
 * etapa). Mesmo cuidado que Fortify já tem para staff via
 * Laravel\Fortify\Actions\CompletePasswordReset.
 */
class ResetPatientPasswordAction
{
    public function handle(PatientUser $patientUser, string $password): void
    {
        $patientUser->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();
    }
}

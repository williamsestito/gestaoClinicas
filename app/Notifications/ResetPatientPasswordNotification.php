<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

/**
 * Mesmo conteúdo do ResetPassword padrão do Laravel, mas apontando para a
 * rota nomeada do portal do paciente — "password.reset" já é usada pelo
 * fluxo de staff (Fortify).
 */
class ResetPatientPasswordNotification extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        return url(route('patient-portal.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Mesmo conteúdo do VerifyEmail padrão do Laravel, mas apontando para a
 * rota nomeada do portal do paciente — "verification.verify" já é usada
 * pelo fluxo de staff (Fortify).
 */
class VerifyPatientEmailNotification extends VerifyEmail
{
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'patient-portal.verification.verify',
            Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }
}

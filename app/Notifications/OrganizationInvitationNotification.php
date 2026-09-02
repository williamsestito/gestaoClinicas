<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Enviado ao convidar alguém para uma organização. O convidado ainda não
 * tem conta (ou pode já ter) — por isso é enviado via rota anônima
 * (`Notification::route('mail', ...)`), nunca associado a um User.
 */
class OrganizationInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Invitation $invitation,
        private readonly string $token,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organizationName = $this->invitation->organization->name;
        $inviterName = $this->invitation->invitedBy->name;
        $url = route('invitations.accept', ['token' => $this->token]);

        return (new MailMessage)
            ->subject("Convite para {$organizationName}")
            ->greeting('Olá!')
            ->line("{$inviterName} convidou você para fazer parte de {$organizationName} no Gestão de Clínicas.")
            ->action('Aceitar convite', $url)
            ->line('Este convite expira em '.$this->invitation->expires_at->format('d/m/Y').'.')
            ->line('Se você não esperava este convite, pode ignorar este e-mail com segurança.');
    }
}

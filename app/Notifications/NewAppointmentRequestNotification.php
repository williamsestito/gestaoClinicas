<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AppointmentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa a clínica sobre uma nova solicitação de agendamento (lead) vinda da
 * landing pública. Implementa ShouldQueue para que uma falha no envio
 * (SMTP indisponível, por exemplo) nunca afete a resposta da submissão
 * pública nem a solicitação já persistida — ver
 * App\Http\Controllers\PublicAppointmentRequestController.
 */
class NewAppointmentRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly AppointmentRequest $appointmentRequest) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->appointmentRequest;
        $relatedService = $request->service;
        $service = $relatedService === null ? 'Não informado' : $relatedService->name;
        $preference = trim(implode(' — ', array_filter([
            $request->preferred_date?->format('d/m/Y'),
            $request->preferred_period,
        ])));

        return (new MailMessage)
            ->subject('Nova solicitação de agendamento')
            ->greeting('Nova solicitação recebida')
            ->line("Nome: {$request->name}")
            ->line("Telefone: {$request->phone}")
            ->line("Serviço: {$service}")
            ->when($preference !== '', fn (MailMessage $mail) => $mail->line("Preferência: {$preference}"))
            ->action('Ver solicitação', route('settings.site.appointment-requests.index'));
    }
}

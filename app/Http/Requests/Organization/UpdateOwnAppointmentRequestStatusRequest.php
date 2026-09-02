<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\AppointmentRequestStatus;
use App\Enums\RecordStatus;
use App\Models\AppointmentRequest;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Autoatendimento — nunca a permissão administrativa
 * (`SiteAppointmentsManage`, ver UpdateAppointmentRequestStatusRequest):
 * só autoriza quando a solicitação está vinculada ao profissional do
 * próprio usuário autenticado (ver App\Http\Controllers\Organization\
 * MyAppointmentRequestsController).
 */
class UpdateOwnAppointmentRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AppointmentRequest|null $appointmentRequest */
        $appointmentRequest = $this->route('appointmentRequest');
        /** @var User|null $user */
        $user = $this->user();
        $organization = app(TenantContext::class)->organization();

        if ($appointmentRequest === null || $user === null || $appointmentRequest->professional_id === null) {
            return false;
        }

        // Checagem explícita de organização, mesmo padrão de
        // AppointmentRequestController::updateStatus() — um usuário pode
        // ter profissional vinculado em mais de uma organização, então o
        // vínculo com o profissional sozinho não garante que a solicitação
        // pertence à organização ativa dele.
        if ($organization === null || $appointmentRequest->organization_id !== $organization->id) {
            return false;
        }

        return $user->professionals()
            ->where('status', RecordStatus::Active)
            ->whereKey($appointmentRequest->professional_id)
            ->exists();
    }

    /**
     * `Scheduled` nunca é aceito aqui: só existe de verdade quando
     * App\Actions\Organization\CreateAppointmentAction cria o Appointment e
     * marca os dois juntos (ver appointment_id) — o profissional converte
     * um pré-agendamento pelo botão "Agendar" (que passa por essa Action),
     * nunca soltando o status sozinho neste select.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AppointmentRequestStatus::class)->except(AppointmentRequestStatus::Scheduled)],
        ];
    }

    /**
     * Mesmo bloqueio de UpdateAppointmentRequestStatusRequest — uma vez
     * convertido (`appointment_id` preenchido), o status não pode mais ser
     * solto por este select, mesmo pelo próprio profissional.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var AppointmentRequest|null $appointmentRequest */
            $appointmentRequest = $this->route('appointmentRequest');

            if ($appointmentRequest?->appointment_id !== null) {
                $validator->errors()->add('status', 'Este pré-agendamento já foi confirmado em um agendamento real — o status não pode mais ser alterado por aqui.');
            }
        });
    }
}

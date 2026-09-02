<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\AppointmentRequestStatus;
use App\Models\AppointmentRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAppointmentRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        return $organization !== null && $this->user()?->can('update', [AppointmentRequest::class, $organization]) === true;
    }

    /**
     * `Scheduled` nunca é aceito aqui: só existe de verdade quando
     * App\Actions\Organization\CreateAppointmentAction cria o Appointment e
     * marca os dois juntos (ver appointment_id) — permitir setar via um
     * select solto deixava o pré-agendamento marcado como "Agendado" sem
     * nenhum agendamento real por trás, invisível na agenda do profissional
     * e no portal do paciente (achado real, corrigido nesta etapa).
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
     * Uma vez convertido em Appointment real (`appointment_id` preenchido),
     * o status deste lead nunca mais pode ser solto por este select — achado
     * real: alguém trocava para "Contato realizado"/"Cancelado" depois da
     * conversão, o registro sumia do filtro "Agendado" mas `appointment_id`
     * continuava preenchido, e uma nova tentativa de confirmar batia no
     * bloqueio de CreateAppointmentAction::assertSourceRequestConvertible()
     * com "já foi confirmado por outro usuário" — confuso, porque não tinha
     * sido ninguém, tinha sido só o próprio status dessincronizado.
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

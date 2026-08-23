<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\AppointmentRequestStatus;
use App\Models\AppointmentRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
}

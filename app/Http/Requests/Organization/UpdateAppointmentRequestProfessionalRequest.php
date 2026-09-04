<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\RecordStatus;
use App\Models\AppointmentRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAppointmentRequestProfessionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        return $organization !== null && $this->user()?->can('update', [AppointmentRequest::class, $organization]) === true;
    }

    /**
     * `null` é aceito — remove a preferência de profissional, deixando o
     * lead "sem profissional definido" (ex.: o profissional originalmente
     * escolhido pelo paciente foi excluído do sistema). Só profissionais
     * ativos e não excluídos da própria organização podem ser escolhidos.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = app(TenantContext::class)->organization()?->id;

        return [
            'professional_id' => [
                'nullable',
                'string',
                Rule::exists('professionals', 'id')
                    ->where('organization_id', $organizationId)
                    ->where('status', RecordStatus::Active->value)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * Mesma trava de UpdateAppointmentRequestStatusRequest: depois de
     * convertido em Appointment real, o profissional deste lead é o do
     * agendamento (ver Appointment::professional_id) — reatribuir por aqui
     * deixaria os dois dessincronizados.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var AppointmentRequest|null $appointmentRequest */
            $appointmentRequest = $this->route('appointmentRequest');

            if ($appointmentRequest?->appointment_id !== null) {
                $validator->errors()->add('professional_id', 'Este pré-agendamento já foi confirmado em um agendamento real — o profissional não pode mais ser alterado por aqui.');
            }
        });
    }
}

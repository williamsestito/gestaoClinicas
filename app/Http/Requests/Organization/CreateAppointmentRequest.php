<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\Appointment;
use App\Models\AppointmentRequest as AppointmentRequestModel;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAppointmentRequest extends FormRequest
{
    /**
     * Quem tem `appointments.manage` sempre autoriza. Um profissional sem
     * essa permissão só autoriza convertendo o PRÓPRIO pré-agendamento (ver
     * AppointmentPolicy::createFromOwnRequest()) — o controller ainda trava
     * separadamente que o `professional_id` enviado bate com o do vínculo
     * (ver AppointmentController::store()), já que esta checagem sozinha não
     * valida os demais campos do formulário.
     */
    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        if ($organization === null) {
            \Log::debug('DEBUG authorize: no organization in TenantContext');

            return false;
        }

        if ($this->user()?->can('create', [Appointment::class, $organization]) === true) {
            return true;
        }

        $sourceRequestId = $this->string('appointment_request_id')->value() ?: null;
        $sourceRequest = $sourceRequestId
            ? AppointmentRequestModel::query()->where('organization_id', $organization->id)->find($sourceRequestId)
            : null;

        \Log::debug('DEBUG authorize', [
            'organization_id' => $organization->id,
            'sourceRequestId' => $sourceRequestId,
            'sourceRequestFound' => $sourceRequest !== null,
            'sourceRequest_org' => $sourceRequest?->organization_id,
            'sourceRequest_prof' => $sourceRequest?->professional_id,
            'can_createFromOwnRequest' => $sourceRequest ? $this->user()?->can('createFromOwnRequest', $sourceRequest) : null,
            'user_id' => $this->user()?->id,
            'all_input' => $this->all(),
        ]);

        return $sourceRequest !== null && $this->user()?->can('createFromOwnRequest', $sourceRequest) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = app(TenantContext::class)->organization()?->id;

        return [
            'unit_id' => ['required', 'string', Rule::exists('units', 'id')->where('organization_id', $organizationId)],
            'professional_id' => ['required', 'string', Rule::exists('professionals', 'id')->where('organization_id', $organizationId)],
            'patient_id' => ['required', 'string', Rule::exists('patients', 'id')->where('organization_id', $organizationId)],
            'service_id' => ['required', 'string', Rule::exists('services', 'id')->where('organization_id', $organizationId)],
            'starts_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Presente quando o agendamento é a conversão de um lead da
            // landing pública (Etapa 3.2) — ver
            // App\Actions\Organization\CreateAppointmentAction.
            'appointment_request_id' => ['nullable', 'string', Rule::exists('appointment_requests', 'id')->where('organization_id', $organizationId)],
            // Recursos compartilhados (salas/equipamentos, Etapa 3.3) —
            // opcionais, revalidados por App\Support\Availability\ResourceOverlapGuard.
            'resource_ids' => ['nullable', 'array'],
            'resource_ids.*' => ['string', Rule::exists('resources', 'id')->where('organization_id', $organizationId)],
            // Pacote de sessões (Etapa 3.3) — opcional, revalidado por
            // App\Actions\Organization\CreateAppointmentAction.
            'session_package_id' => ['nullable', 'string', Rule::exists('session_packages', 'id')->where('organization_id', $organizationId)],
            // Recorrência semanal (Etapa 3.3) — número de ocorrências (a
            // própria contém a 1ª), revalidado por
            // App\Actions\Organization\CreateRecurringAppointmentSeriesAction.
            'recurrence_weeks' => ['nullable', 'integer', 'min:2', 'max:52'],
            // Presente quando o agendamento é a conversão de uma entrada da
            // lista de espera (Etapa 3.3) — ver
            // App\Actions\Organization\CreateAppointmentAction::convertSourceWaitlistEntry().
            'waitlist_entry_id' => ['nullable', 'string', Rule::exists('appointment_waitlist_entries', 'id')->where('organization_id', $organizationId)],
        ];
    }
}

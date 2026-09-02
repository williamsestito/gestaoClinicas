<?php

declare(strict_types=1);

namespace App\Http\Requests\PatientPortal;

use App\Models\PatientUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sem restrição `is_public` no serviço (diferente de
 * PublicAvailabilityProfessionalsRequest) — um paciente autenticado pode
 * agendar qualquer serviço ativo da própria organização, não só os
 * marcados para vitrine pública do site.
 */
class PatientAvailabilityProfessionalsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        /** @var PatientUser $patientUser */
        $patientUser = $this->user('patient');
        $organizationId = $patientUser->organization_id;

        return [
            'unit_id' => ['required', 'string', 'ulid', Rule::exists('units', 'id')->where('organization_id', $organizationId)->where('status', 'active')],
            'service_id' => ['required', 'string', 'ulid', Rule::exists('services', 'id')->where('organization_id', $organizationId)->where('status', 'active')],
            'specialty_id' => ['nullable', 'string', 'ulid', Rule::exists('specialties', 'id')->where('organization_id', $organizationId)->where('status', 'active')],
        ];
    }
}

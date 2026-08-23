<?php

declare(strict_types=1);

namespace App\Http\Requests\PatientPortal;

use App\Models\PatientUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mesma cadeia de disponibilidade da busca pública (ver
 * App\Services\Availability\PublicAvailabilityFinder), mas escopada pela
 * organização do paciente autenticado — nunca por Organization::first()
 * como a versão pública (ver PublicAvailabilitySpecialtiesRequest).
 */
class PatientAvailabilitySpecialtiesRequest extends FormRequest
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
        ];
    }
}

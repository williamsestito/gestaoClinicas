<?php

declare(strict_types=1);

namespace App\Http\Requests\PatientPortal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sem Policy (mesmo padrão do resto do portal, ver
 * docs/modules/patient-portal.md) — a autorização é o middleware
 * auth:patient/patient.active mais o escopo do Patient via
 * PatientUser::patients() no controller. Sem `patient_id` (vem da rota).
 */
class BookAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'string'],
            'professional_id' => ['required', 'string'],
            'service_id' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

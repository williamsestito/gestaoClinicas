<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\Appointment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateWaitlistEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        return $organization !== null && $this->user()?->can('create', [Appointment::class, $organization]) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = app(TenantContext::class)->organization()?->id;

        return [
            'unit_id' => ['required', 'string', Rule::exists('units', 'id')->where('organization_id', $organizationId)],
            'professional_id' => ['nullable', 'string', Rule::exists('professionals', 'id')->where('organization_id', $organizationId)],
            'service_id' => ['required', 'string', Rule::exists('services', 'id')->where('organization_id', $organizationId)],
            'patient_id' => ['required', 'string', Rule::exists('patients', 'id')->where('organization_id', $organizationId)],
            'preferred_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

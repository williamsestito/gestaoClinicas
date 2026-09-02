<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        return $organization !== null && $this->user()?->can('update', $organization) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'default_timezone' => ['required', 'string', 'timezone'],
            'default_currency' => ['required', 'string', 'size:3'],
            'locale' => ['required', 'string', 'max:10'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            // "Encaixe" configurável (Etapa 3.3) — ver
            // App\Support\Availability\AppointmentOverlapGuard. Opcional:
            // quando ausente, UpdateOrganizationAction preserva o valor
            // atual (collect(...)->only() nunca zera uma chave ausente).
            'allow_appointment_overlap' => ['sometimes', 'boolean'],
        ];
    }
}

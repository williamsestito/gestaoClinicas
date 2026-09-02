<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicAvailabilityServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $organizationId = Organization::query()->first()?->id;

        return [
            'unit_id' => ['required', 'string', 'ulid', Rule::exists('units', 'id')->where('organization_id', $organizationId)->where('status', 'active')],
            'specialty_id' => ['nullable', 'string', 'ulid', Rule::exists('specialties', 'id')->where('organization_id', $organizationId)->where('status', 'active')],
        ];
    }
}

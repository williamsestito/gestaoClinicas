<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\SharedResource;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        return $organization !== null && $this->user()?->can('create', [SharedResource::class, $organization]) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => CreateSpecialtyRequest::normalizeSpaces($this->input('name')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = app(TenantContext::class)->organization()?->id;

        return [
            'unit_id' => ['required', 'string', Rule::exists('units', 'id')->where('organization_id', $organizationId)],
            'name' => [
                'required', 'string', 'min:2', 'max:255',
                Rule::unique('resources', 'name')->where('organization_id', $organizationId)->where('unit_id', $this->input('unit_id')),
            ],
            'type' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Já existe um recurso com este nome nesta unidade.',
        ];
    }
}

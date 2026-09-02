<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\SharedResource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('resource')) === true;
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
        /** @var SharedResource|null $resource */
        $resource = $this->route('resource');
        $organizationId = $resource?->organization_id;

        return [
            'unit_id' => ['required', 'string', Rule::exists('units', 'id')->where('organization_id', $organizationId)],
            'name' => [
                'required', 'string', 'min:2', 'max:255',
                Rule::unique('resources', 'name')->where('organization_id', $organizationId)->where('unit_id', $this->input('unit_id'))->ignore($resource?->id),
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

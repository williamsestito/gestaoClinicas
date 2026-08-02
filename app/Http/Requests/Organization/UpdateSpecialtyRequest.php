<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\Specialty;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSpecialtyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('specialty')) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => CreateSpecialtyRequest::normalizeSpaces($this->input('name')),
            'code' => CreateSpecialtyRequest::normalizeCode($this->input('code')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Specialty|null $specialty */
        $specialty = $this->route('specialty');
        $organizationId = $specialty?->organization_id;

        return [
            'name' => [
                'required', 'string', 'min:2', 'max:255',
                Rule::unique('specialties', 'name')->where('organization_id', $organizationId)->ignore($specialty?->id),
            ],
            'code' => [
                'nullable', 'string', 'max:30', 'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('specialties', 'code')->where('organization_id', $organizationId)->ignore($specialty?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Já existe uma especialidade com este nome.',
            'code.unique' => 'Já existe uma especialidade com este código.',
            'code.regex' => 'O código deve conter apenas letras maiúsculas, números e hífen.',
        ];
    }
}

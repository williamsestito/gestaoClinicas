<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\ServiceAvailabilityScope;
use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('service')) === true;
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
        /** @var Service|null $service */
        $service = $this->route('service');
        $organizationId = $service?->organization_id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('services', 'code')->where('organization_id', $organizationId)->ignore($service?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'buffer_before_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'buffer_after_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'default_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_public' => ['boolean'],
            'requires_manual_confirmation' => ['boolean'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'unit_availability_scope' => ['required', Rule::enum(ServiceAvailabilityScope::class)],
            'specialty_ids' => ['nullable', 'array'],
            'specialty_ids.*' => [
                'string',
                Rule::exists('specialties', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at'),
            ],
            'unit_ids' => ['nullable', 'array'],
            'unit_ids.*' => [
                'string',
                Rule::exists('units', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $total = (int) $this->input('default_duration_minutes', 0)
                + (int) $this->input('buffer_before_minutes', 0)
                + (int) $this->input('buffer_after_minutes', 0);

            if ($total > 1440) {
                $validator->errors()->add('default_duration_minutes', 'A duração somada aos intervalos não pode ultrapassar 24 horas.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Já existe um serviço com este código.',
            'code.regex' => 'O código deve conter apenas letras maiúsculas, números e hífen.',
            'color.regex' => 'Informe uma cor no formato hexadecimal (ex.: #2563EB).',
            'specialty_ids.*.exists' => 'Uma das especialidades selecionadas não pertence a esta clínica ou não está disponível.',
            'unit_ids.*.exists' => 'Uma das unidades selecionadas não pertence a esta clínica ou não está disponível.',
        ];
    }

    /** @return array{name: string, code: string, description: ?string, default_duration_minutes: int, buffer_before_minutes: int, buffer_after_minutes: int, default_price_cents: ?int, color: ?string, is_public: bool, requires_manual_confirmation: bool, internal_notes: ?string, unit_availability_scope: string, specialty_ids: array<int, string>, unit_ids: array<int, string>} */
    public function attributesForAction(): array
    {
        $price = $this->input('default_price');

        return [
            'name' => (string) $this->input('name'),
            'code' => (string) $this->input('code'),
            'description' => $this->input('description'),
            'default_duration_minutes' => (int) $this->input('default_duration_minutes'),
            'buffer_before_minutes' => (int) $this->input('buffer_before_minutes', 0),
            'buffer_after_minutes' => (int) $this->input('buffer_after_minutes', 0),
            'default_price_cents' => $price === null || $price === '' ? null : (int) round(((float) $price) * 100),
            'color' => $this->input('color'),
            'is_public' => $this->boolean('is_public'),
            'requires_manual_confirmation' => $this->boolean('requires_manual_confirmation'),
            'internal_notes' => $this->input('internal_notes'),
            'unit_availability_scope' => (string) $this->input('unit_availability_scope'),
            'specialty_ids' => array_values(array_unique((array) $this->input('specialty_ids', []))),
            'unit_ids' => array_values(array_unique((array) $this->input('unit_ids', []))),
        ];
    }
}

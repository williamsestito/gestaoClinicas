<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\ProfessionalServiceUnitScope;
use App\Enums\RecordStatus;
use App\Models\Professional;
use App\Models\ProfessionalService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignProfessionalServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageServices', $this->route('professional')) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Professional|null $professional */
        $professional = $this->route('professional');
        $organizationId = $professional?->organization_id;

        return [
            'service_id' => [
                'required', 'string',
                Rule::exists('services', 'id')
                    ->where('organization_id', $organizationId)
                    ->where('status', RecordStatus::Active->value)
                    ->whereNull('deleted_at'),
            ],
            'custom_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'custom_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'custom_buffer_before_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'custom_buffer_after_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'unit_scope' => ['required', Rule::enum(ProfessionalServiceUnitScope::class)],
            'unit_ids' => ['required_if:unit_scope,selected_units', 'array'],
            'unit_ids.*' => [
                'string',
                Rule::exists('units', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Professional|null $professional */
            $professional = $this->route('professional');

            if (! $professional || ! $this->filled('service_id')) {
                return;
            }

            $alreadyLinked = ProfessionalService::query()
                ->where('professional_id', $professional->id)
                ->where('service_id', $this->input('service_id'))
                ->exists();

            if ($alreadyLinked) {
                $validator->errors()->add('service_id', 'Já existe um vínculo ativo com este serviço.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'service_id.exists' => 'Selecione um serviço ativo desta clínica.',
            'unit_ids.required_if' => 'Selecione ao menos uma unidade.',
            'unit_ids.*.exists' => 'Uma das unidades selecionadas não pertence a esta clínica ou não está disponível.',
        ];
    }

    /**
     * @return array{service_id: string, custom_duration_minutes: ?int, custom_price_cents: ?int, custom_buffer_before_minutes: ?int, custom_buffer_after_minutes: ?int, unit_scope: string, unit_ids: array<int, string>}
     */
    public function attributesForAction(): array
    {
        $price = $this->input('custom_price');

        return [
            'service_id' => (string) $this->input('service_id'),
            'custom_duration_minutes' => $this->filled('custom_duration_minutes') ? (int) $this->input('custom_duration_minutes') : null,
            'custom_price_cents' => $price === null || $price === '' ? null : (int) round(((float) $price) * 100),
            'custom_buffer_before_minutes' => $this->filled('custom_buffer_before_minutes') ? (int) $this->input('custom_buffer_before_minutes') : null,
            'custom_buffer_after_minutes' => $this->filled('custom_buffer_after_minutes') ? (int) $this->input('custom_buffer_after_minutes') : null,
            'unit_scope' => (string) $this->input('unit_scope'),
            'unit_ids' => array_values(array_unique((array) $this->input('unit_ids', []))),
        ];
    }
}

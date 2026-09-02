<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\RecordStatus;
use App\Models\Professional;
use App\Models\ProfessionalUnit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignProfessionalUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageUnits', $this->route('professional')) === true;
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
            'unit_id' => [
                'required', 'string',
                Rule::exists('units', 'id')
                    ->where('organization_id', $organizationId)
                    ->where('status', RecordStatus::Active->value)
                    ->whereNull('deleted_at'),
            ],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Professional|null $professional */
            $professional = $this->route('professional');

            if (! $professional || ! $this->filled('unit_id')) {
                return;
            }

            $alreadyLinked = ProfessionalUnit::query()
                ->where('professional_id', $professional->id)
                ->where('unit_id', $this->input('unit_id'))
                ->exists();

            if ($alreadyLinked) {
                $validator->errors()->add('unit_id', 'Já existe um vínculo ativo com esta unidade.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'unit_id.exists' => 'Selecione uma unidade ativa desta clínica.',
            'ends_on.after_or_equal' => 'O fim da vigência não pode ser anterior ao início.',
        ];
    }
}

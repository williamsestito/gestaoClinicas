<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\RecordStatus;
use App\Models\Professional;
use App\Models\ProfessionalSpecialty;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignProfessionalSpecialtyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSpecialties', $this->route('professional')) === true;
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
            'specialty_id' => [
                'required', 'string',
                Rule::exists('specialties', 'id')
                    ->where('organization_id', $organizationId)
                    ->where('status', RecordStatus::Active->value)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Professional|null $professional */
            $professional = $this->route('professional');

            if (! $professional || ! $this->filled('specialty_id')) {
                return;
            }

            $alreadyLinked = ProfessionalSpecialty::query()
                ->where('professional_id', $professional->id)
                ->where('specialty_id', $this->input('specialty_id'))
                ->exists();

            if ($alreadyLinked) {
                $validator->errors()->add('specialty_id', 'Já existe um vínculo ativo com esta especialidade.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'specialty_id.exists' => 'Selecione uma especialidade ativa desta clínica.',
        ];
    }
}

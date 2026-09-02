<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\Weekday;
use App\Models\Professional;
use App\Models\ProfessionalUnit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignProfessionalWorkingHourRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Professional|null $professional */
        $professional = $this->route('professional');
        /** @var ProfessionalUnit|null $professionalUnit */
        $professionalUnit = $this->route('professionalUnit');

        if (! $professional || ! $professionalUnit) {
            return false;
        }

        return $this->user()?->can('manageAvailability', [$professional, $professionalUnit->unit]) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'weekday' => ['required', Rule::enum(Weekday::class)],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var ProfessionalUnit|null $professionalUnit */
            $professionalUnit = $this->route('professionalUnit');

            if (! $professionalUnit || $professionalUnit->trashed()) {
                $validator->errors()->add('weekday', 'O vínculo com a unidade não está mais disponível.');

                return;
            }

            if ($professionalUnit->unit === null || $professionalUnit->unit->trashed()) {
                $validator->errors()->add('weekday', 'A unidade não está mais disponível.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'ends_at.after' => 'O horário final deve ser posterior ao inicial.',
            'effective_until.after_or_equal' => 'O fim da vigência não pode ser anterior ao início.',
        ];
    }

    /** @return array{weekday: int, starts_at: string, ends_at: string, effective_from: ?string, effective_until: ?string} */
    public function attributesForAction(): array
    {
        return [
            'weekday' => (int) $this->input('weekday'),
            'starts_at' => (string) $this->input('starts_at'),
            'ends_at' => (string) $this->input('ends_at'),
            'effective_from' => $this->input('effective_from'),
            'effective_until' => $this->input('effective_until'),
        ];
    }
}

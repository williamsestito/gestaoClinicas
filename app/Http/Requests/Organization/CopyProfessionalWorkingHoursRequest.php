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

class CopyProfessionalWorkingHoursRequest extends FormRequest
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
            'source_weekday' => ['required', Rule::enum(Weekday::class)],
            'target_weekdays' => ['required', 'array', 'min:1'],
            'target_weekdays.*' => ['distinct', Rule::enum(Weekday::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('source_weekday') || ! is_array($this->input('target_weekdays'))) {
                return;
            }

            if (in_array((int) $this->input('source_weekday'), array_map('intval', $this->input('target_weekdays')), true)) {
                $validator->errors()->add('target_weekdays', 'O dia de destino não pode ser igual ao dia de origem.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'target_weekdays.required' => 'Selecione ao menos um dia de destino.',
            'target_weekdays.min' => 'Selecione ao menos um dia de destino.',
        ];
    }

    /** @return array{source_weekday: int, target_weekdays: array<int, int>} */
    public function attributesForAction(): array
    {
        return [
            'source_weekday' => (int) $this->input('source_weekday'),
            'target_weekdays' => array_map('intval', $this->input('target_weekdays', [])),
        ];
    }
}

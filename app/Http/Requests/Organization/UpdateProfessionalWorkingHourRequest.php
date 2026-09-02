<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\Weekday;
use App\Models\Professional;
use App\Models\ProfessionalWorkingHour;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfessionalWorkingHourRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Professional|null $professional */
        $professional = $this->route('professional');
        /** @var ProfessionalWorkingHour|null $workingHour */
        $workingHour = $this->route('workingHour');

        if (! $professional || ! $workingHour) {
            return false;
        }

        return $this->user()?->can('manageAvailability', [$professional, $workingHour->professionalUnit->unit]) === true;
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

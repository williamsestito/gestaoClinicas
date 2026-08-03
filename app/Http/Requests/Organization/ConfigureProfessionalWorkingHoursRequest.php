<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\Weekday;
use App\Models\Professional;
use App\Models\ProfessionalUnit;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConfigureProfessionalWorkingHoursRequest extends FormRequest
{
    /** Limite máximo de dias em uma única configuração de vigência em lote — evita períodos sem sentido prático. */
    private const MAX_VIGENCY_DAYS = 730;

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

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['distinct', Rule::enum(Weekday::class)],
            'intervals' => ['required', 'array', 'min:1'],
            'intervals.*.starts_at' => ['required', 'date_format:H:i'],
            'intervals.*.ends_at' => ['required', 'date_format:H:i'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['required', 'date', 'after_or_equal:effective_from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var ProfessionalUnit|null $professionalUnit */
            $professionalUnit = $this->route('professionalUnit');

            if (! $professionalUnit || $professionalUnit->trashed()) {
                $validator->errors()->add('weekdays', 'O vínculo com a unidade não está mais disponível.');

                return;
            }

            if ($professionalUnit->unit === null || $professionalUnit->unit->trashed()) {
                $validator->errors()->add('weekdays', 'A unidade não está mais disponível.');
            }

            /** @var array<int, array{starts_at?: string, ends_at?: string}> $intervals */
            $intervals = is_array($this->input('intervals')) ? $this->input('intervals') : [];

            foreach ($intervals as $index => $interval) {
                if (! empty($interval['starts_at']) && ! empty($interval['ends_at']) && $interval['starts_at'] >= $interval['ends_at']) {
                    $validator->errors()->add("intervals.{$index}.ends_at", 'O horário final deve ser posterior ao inicial.');
                }
            }

            if (! $this->filled('effective_from') || ! $this->filled('effective_until')) {
                return;
            }

            $from = CarbonImmutable::parse((string) $this->input('effective_from'));
            $until = CarbonImmutable::parse((string) $this->input('effective_until'));

            if ($from->diffInDays($until) > self::MAX_VIGENCY_DAYS) {
                $validator->errors()->add('effective_until', 'O período de vigência não pode ultrapassar '.self::MAX_VIGENCY_DAYS.' dias.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'weekdays.required' => 'Selecione ao menos um dia da semana.',
            'weekdays.min' => 'Selecione ao menos um dia da semana.',
            'intervals.required' => 'Adicione ao menos um intervalo.',
            'intervals.min' => 'Adicione ao menos um intervalo.',
            'effective_from.required' => 'Informe a data inicial da vigência.',
            'effective_until.required' => 'Informe a data final da vigência.',
            'effective_until.after_or_equal' => 'A data final não pode ser anterior à data inicial.',
        ];
    }

    /** @return array{weekdays: array<int, int>, intervals: array<int, array{starts_at: string, ends_at: string}>, effective_from: string, effective_until: string} */
    public function attributesForAction(): array
    {
        /** @var array<int, array{starts_at: string, ends_at: string}> $intervals */
        $intervals = $this->input('intervals', []);

        return [
            'weekdays' => array_map('intval', $this->input('weekdays', [])),
            'intervals' => array_map(fn (array $interval) => [
                'starts_at' => (string) $interval['starts_at'],
                'ends_at' => (string) $interval['ends_at'],
            ], $intervals),
            'effective_from' => (string) $this->input('effective_from'),
            'effective_until' => (string) $this->input('effective_until'),
        ];
    }
}

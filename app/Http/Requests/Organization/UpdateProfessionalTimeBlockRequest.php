<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\ProfessionalTimeBlockScope;
use App\Enums\ProfessionalTimeBlockType;
use App\Models\Professional;
use App\Models\ProfessionalTimeBlock;
use App\Models\ProfessionalUnit;
use App\Models\Unit;
use App\Support\Availability\LocalTimeConverter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfessionalTimeBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Professional|null $professional */
        $professional = $this->route('professional');
        /** @var ProfessionalTimeBlock|null $timeBlock */
        $timeBlock = $this->route('timeBlock');

        if (! $professional || ! $timeBlock) {
            return false;
        }

        $unit = $this->filled('unit_id') ? Unit::query()->find($this->input('unit_id')) : $timeBlock->unit;

        return $this->user()?->can('manageTimeBlocks', [$professional, $unit]) === true;
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
            'type' => ['required', Rule::enum(ProfessionalTimeBlockType::class)],
            'scope' => ['required', Rule::enum(ProfessionalTimeBlockScope::class)],
            'unit_id' => [
                'nullable', 'required_if:scope,specific_unit', 'string',
                Rule::exists('units', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at'),
            ],
            'is_all_day' => ['boolean'],
            'starts_date' => ['required', 'date_format:Y-m-d'],
            'starts_time' => ['nullable', 'date_format:H:i'],
            'ends_date' => ['required', 'date_format:Y-m-d'],
            'ends_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:255'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Evita depender de required_unless com um campo booleano: o
            // valor bruto de is_all_day pode chegar como bool (JSON) ou
            // string, e a comparação da regra não é confiável entre os
            // dois formatos.
            if (! $this->boolean('is_all_day')) {
                if (! $this->filled('starts_time')) {
                    $validator->errors()->add('starts_time', 'Informe o horário inicial ou marque "dia inteiro".');
                }
                if (! $this->filled('ends_time')) {
                    $validator->errors()->add('ends_time', 'Informe o horário final ou marque "dia inteiro".');
                }
            }

            /** @var Professional|null $professional */
            $professional = $this->route('professional');

            if (! $professional || $this->input('scope') !== ProfessionalTimeBlockScope::SpecificUnit->value || ! $this->filled('unit_id')) {
                return;
            }

            $hasLink = ProfessionalUnit::query()
                ->where('professional_id', $professional->id)
                ->where('unit_id', $this->input('unit_id'))
                ->exists();

            if (! $hasLink) {
                $validator->errors()->add('unit_id', 'O profissional não possui vínculo com esta unidade.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'unit_id.required_if' => 'Selecione a unidade do bloqueio.',
            'unit_id.exists' => 'Selecione uma unidade desta clínica.',
        ];
    }

    /** @return array{type: string, scope: string, unit_id: ?string, starts_at: Carbon, ends_at: Carbon, is_all_day: bool, reason: ?string, internal_notes: ?string} */
    public function attributesForAction(): array
    {
        /** @var Professional $professional */
        $professional = $this->route('professional');
        $scope = ProfessionalTimeBlockScope::from((string) $this->input('scope'));
        $unitId = $scope === ProfessionalTimeBlockScope::SpecificUnit ? (string) $this->input('unit_id') : null;
        $timezone = $unitId !== null
            ? (Unit::query()->findOrFail($unitId)->timezone)
            : $professional->referenceTimezone();

        $isAllDay = $this->boolean('is_all_day');

        if ($isAllDay) {
            $startsAt = LocalTimeConverter::startOfLocalDayInUtc((string) $this->input('starts_date'), $timezone);
            $endsAt = LocalTimeConverter::startOfNextLocalDayInUtc((string) $this->input('ends_date'), $timezone);
        } else {
            $startsAt = LocalTimeConverter::toUtc((string) $this->input('starts_date'), (string) $this->input('starts_time'), $timezone);
            $endsAt = LocalTimeConverter::toUtc((string) $this->input('ends_date'), (string) $this->input('ends_time'), $timezone);
        }

        return [
            'type' => (string) $this->input('type'),
            'scope' => $scope->value,
            'unit_id' => $unitId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_all_day' => $isAllDay,
            'reason' => $this->input('reason'),
            'internal_notes' => $this->input('internal_notes'),
        ];
    }
}

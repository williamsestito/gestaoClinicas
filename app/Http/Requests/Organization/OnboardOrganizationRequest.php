<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Data\Organization\OpeningHourData;
use App\Enums\LegalEntityType;
use App\Rules\CpfCnpjRule;
use App\Support\Documents\BrazilianState;
use App\Support\OpeningHoursOverlapGuard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class OnboardOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Qualquer usuário autenticado e verificado pode iniciar seu próprio
        // onboarding (nenhum vínculo prévio é necessário) — ver routes/clinic.php.
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:255'],

            'legal_entity_type' => ['required', Rule::enum(LegalEntityType::class)],
            'document' => ['required', 'string', new CpfCnpjRule($this->legalEntityType())],
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],

            'unit_name' => ['required', 'string', 'max:255'],
            'unit_phone' => ['nullable', 'string', 'max:20'],
            'unit_whatsapp' => ['nullable', 'string', 'max:20'],

            'address.postal_code' => ['required', 'string'],
            'address.street' => ['required', 'string', 'max:255'],
            'address.number' => ['required', 'string', 'max:20'],
            'address.complement' => ['nullable', 'string', 'max:255'],
            'address.neighborhood' => ['required', 'string', 'max:255'],
            'address.city' => ['required', 'string', 'max:255'],
            'address.state' => ['required', 'string', 'size:2', Rule::in(BrazilianState::codes())],

            'opening_hours' => ['array'],
            'opening_hours.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'opening_hours.*.opens_at' => ['required', 'date_format:H:i'],
            'opening_hours.*.closes_at' => ['required', 'date_format:H:i', 'after:opening_hours.*.opens_at'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $openingHours = [];
            foreach ((array) $this->input('opening_hours', []) as $index => $hour) {
                $openingHours[] = OpeningHourData::fromArray($hour, (int) $index);
            }

            try {
                OpeningHoursOverlapGuard::assertNoOverlap($openingHours);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });
    }

    private function legalEntityType(): LegalEntityType
    {
        return LegalEntityType::tryFrom((string) $this->input('legal_entity_type')) ?? LegalEntityType::Individual;
    }
}

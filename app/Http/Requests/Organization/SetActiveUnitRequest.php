<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SetActiveUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A validação real de vínculo acontece em SetActiveUnitAction (nunca
        // confiamos apenas no ID enviado pelo frontend).
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'string', 'exists:units,id'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\Patient;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSessionPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('patient')) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Patient|null $patient */
        $patient = $this->route('patient');
        $organizationId = $patient?->organization_id;

        return [
            'service_id' => ['nullable', 'string', Rule::exists('services', 'id')->where('organization_id', $organizationId)],
            'total_sessions' => ['required', 'integer', 'min:1', 'max:999'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }
}

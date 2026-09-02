<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\Specialty;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSpecialtyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        return $organization !== null && $this->user()?->can('create', [Specialty::class, $organization]) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => self::normalizeSpaces($this->input('name')),
            'code' => self::normalizeCode($this->input('code')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = app(TenantContext::class)->organization()?->id;

        return [
            'name' => [
                'required', 'string', 'min:2', 'max:255',
                Rule::unique('specialties', 'name')->where('organization_id', $organizationId),
            ],
            'code' => [
                'nullable', 'string', 'max:30', 'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('specialties', 'code')->where('organization_id', $organizationId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Já existe uma especialidade com este nome.',
            'code.unique' => 'Já existe uma especialidade com este código.',
            'code.regex' => 'O código deve conter apenas letras maiúsculas, números e hífen.',
        ];
    }

    public static function normalizeSpaces(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return $normalized === '' ? null : $normalized;
    }

    public static function normalizeCode(mixed $value): ?string
    {
        $normalized = self::normalizeSpaces($value);

        return $normalized === null ? null : mb_strtoupper($normalized);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\OrganizationModule;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        return $organization !== null
            && $this->user()?->can('manage', [OrganizationModule::class, $organization]) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'modules' => ['required', 'array'],
            'modules.dental' => ['sometimes', 'boolean'],
            'modules.aesthetics' => ['sometimes', 'boolean'],
            'modules.medical' => ['sometimes', 'boolean'],
            'modules.beauty' => ['sometimes', 'boolean'],
        ];
    }
}

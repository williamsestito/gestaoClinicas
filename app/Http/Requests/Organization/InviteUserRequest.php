<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        // [OrganizationMembership::class, ...] direciona ao Policy correto
        // (OrganizationMembershipPolicy) — usar Organization::class aqui
        // resolveria a OrganizationPolicy já existente, que não tem "invite".
        return $organization !== null && $this->user()?->can('invite', [OrganizationMembership::class, $organization]) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = app(TenantContext::class)->organization();

        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role_id' => [
                'nullable',
                'string',
                Rule::exists(Role::class, 'id')->where('organization_id', $organization?->id),
            ],
            'unit_ids' => ['array'],
            'unit_ids.*' => ['string', 'exists:units,id'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Rules\NotOwnerRoleRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('membership')) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var OrganizationMembership|null $membership */
        $membership = $this->route('membership');
        $organizationId = $membership?->organization_id;

        return [
            'role_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists(Role::class, 'id')->where('organization_id', $organizationId),
                new NotOwnerRoleRule,
            ],
            'admin_note' => ['sometimes', 'nullable', 'string', 'max:255'],
            'unit_ids' => ['sometimes', 'array'],
            'unit_ids.*' => ['string', 'exists:units,id'],
            'primary_unit_id' => ['sometimes', 'nullable', 'string'],
        ];
    }
}

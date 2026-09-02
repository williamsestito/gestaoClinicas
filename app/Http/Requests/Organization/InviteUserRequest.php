<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Rules\NotOwnerRoleRule;
use App\Support\Tenancy\TenantContext;
use Closure;
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
     * @return array<string, ValidationRule|array<mixed>|string|Closure>
     */
    public function rules(): array
    {
        $organization = app(TenantContext::class)->organization();

        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($organization): void {
                    if ($organization === null) {
                        return;
                    }

                    $hasPendingInvitation = Invitation::query()
                        ->where('organization_id', $organization->id)
                        ->where('email', $value)
                        ->where('status', InvitationStatus::Pending)
                        ->where('expires_at', '>', now())
                        ->exists();

                    if ($hasPendingInvitation) {
                        $fail('Já existe um convite pendente para este e-mail. Utilize reenviar em vez de criar um novo.');
                    }
                },
            ],
            'role_id' => [
                'nullable',
                'string',
                Rule::exists(Role::class, 'id')->where('organization_id', $organization?->id),
                new NotOwnerRoleRule,
            ],
            'unit_ids' => ['array'],
            'unit_ids.*' => ['string', Rule::exists('units', 'id')->where('organization_id', $organization?->id)],
        ];
    }
}

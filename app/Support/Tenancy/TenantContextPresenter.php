<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationStatus;
use App\Enums\RecordStatus;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Formata o contexto ativo (organização/unidade) para envio ao frontend via
 * Inertia — nunca os Models completos (evita vazar CPF/CNPJ e outros dados
 * sensíveis desnecessários). Só lista organizações/unidades ativas e às
 * quais o usuário realmente tem vínculo ativo.
 */
class TenantContextPresenter
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly PermissionChecker $permissionChecker,
    ) {}

    /** @return array<string, mixed>|null */
    public function toArray(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $organization = $this->tenant->organization();
        $membership = $this->tenant->membership();
        $unit = $this->tenant->unit();
        $unitMembership = $this->tenant->unitMembership();

        $availableOrganizations = $user->organizationMemberships()
            ->with('organization')
            ->where('status', OrganizationMembershipStatus::Active)
            ->get()
            ->pluck('organization')
            ->filter(fn ($org) => $org && $org->status === OrganizationStatus::Active)
            ->map(fn ($org) => [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
            ])
            ->values();

        $availableUnits = $membership
            ? $membership->unitMemberships()
                ->with('unit')
                ->where('status', RecordStatus::Active)
                ->get()
                ->pluck('unit')
                ->filter(fn ($u) => $u && $u->status === RecordStatus::Active)
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'code' => $u->code,
                    'is_headquarters' => $u->is_headquarters,
                ])
                ->values()
            : collect();

        return [
            'organization' => $organization ? [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'status' => $organization->status->value,
            ] : null,
            'unit' => $unit ? [
                'id' => $unit->id,
                'name' => $unit->name,
                'code' => $unit->code,
                'is_headquarters' => $unit->is_headquarters,
                'status' => $unit->status->value,
            ] : null,
            'membership' => $membership ? [
                'id' => $membership->id,
                'status' => $membership->status->value,
                'is_owner' => $membership->is_owner,
            ] : null,
            'availableOrganizations' => $availableOrganizations,
            'availableUnits' => $availableUnits,
            'isOwner' => (bool) $membership?->is_owner,
            'isUnitManager' => (bool) $unitMembership?->is_manager,
            'isPlatformAdmin' => $user->is_platform_admin,
            // Só para refletir a navegação/UI — nunca a fonte de verdade da
            // autorização, que é sempre revalidada no backend (Policies).
            'permissions' => $organization ? $this->permissionChecker->effectivePermissions($user, $organization->id) : [],
        ];
    }
}

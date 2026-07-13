<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Models\Organization;
use App\Models\Unit;
use App\Models\User;

/**
 * Gestão de unidades é restrita ao proprietário da organização nesta fase
 * (não há papéis/permissões detalhados ainda). Qualquer membro ativo da
 * organização pode visualizar.
 */
class UnitPolicy
{
    public function view(User $user, Unit $unit): bool
    {
        return $this->hasActiveMembership($user, $unit->organization_id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization->id, requireOwner: true);
    }

    public function update(User $user, Unit $unit): bool
    {
        return $this->hasActiveMembership($user, $unit->organization_id, requireOwner: true);
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $this->hasActiveMembership($user, $unit->organization_id, requireOwner: true);
    }

    public function restore(User $user, Unit $unit): bool
    {
        return $this->hasActiveMembership($user, $unit->organization_id, requireOwner: true);
    }

    private function hasActiveMembership(User $user, string $organizationId, bool $requireOwner = false): bool
    {
        $query = $user->organizationMemberships()
            ->where('organization_id', $organizationId)
            ->where('status', OrganizationMembershipStatus::Active);

        if ($requireOwner) {
            $query->where('is_owner', true);
        }

        return $query->exists();
    }
}

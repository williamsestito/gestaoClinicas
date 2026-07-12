<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Models\Organization;
use App\Models\User;

/**
 * Nesta fase, apenas o proprietário (organization_membership.is_owner=true)
 * pode alterar a organização. Qualquer membro ativo pode visualizá-la.
 * Nenhuma exclusão física é permitida.
 */
class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization, requireOwner: true);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return false;
    }

    private function hasActiveMembership(User $user, Organization $organization, bool $requireOwner = false): bool
    {
        $query = $user->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->where('status', OrganizationMembershipStatus::Active);

        if ($requireOwner) {
            $query->where('is_owner', true);
        }

        return $query->exists();
    }
}

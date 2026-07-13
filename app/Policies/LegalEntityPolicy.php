<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\User;

/**
 * Gestão da entidade legal é restrita ao proprietário da organização
 * nesta fase. Qualquer membro ativo pode visualizar.
 */
class LegalEntityPolicy
{
    public function view(User $user, LegalEntity $legalEntity): bool
    {
        return $this->hasActiveMembership($user, $legalEntity->organization_id);
    }

    public function update(User $user, LegalEntity $legalEntity): bool
    {
        return $this->hasActiveMembership($user, $legalEntity->organization_id, requireOwner: true);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization->id, requireOwner: true);
    }

    public function delete(User $user, LegalEntity $legalEntity): bool
    {
        return $this->hasActiveMembership($user, $legalEntity->organization_id, requireOwner: true);
    }

    public function restore(User $user, LegalEntity $legalEntity): bool
    {
        return $this->hasActiveMembership($user, $legalEntity->organization_id, requireOwner: true);
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

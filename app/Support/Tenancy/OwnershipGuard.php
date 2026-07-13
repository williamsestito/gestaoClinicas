<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\OrganizationMembershipStatus;
use App\Models\OrganizationMembership;

/**
 * Centraliza a regra "uma organização sempre precisa de pelo menos um
 * proprietário ativo" — usada para bloquear desativação de conta,
 * desativação de vínculo e remoção de acesso à unidade matriz quando isso
 * deixaria a organização sem nenhum proprietário ativo.
 */
class OwnershipGuard
{
    public static function isSoleActiveOwner(OrganizationMembership $membership): bool
    {
        if (! $membership->is_owner || $membership->status !== OrganizationMembershipStatus::Active) {
            return false;
        }

        return ! $membership->organization
            ->memberships()
            ->where('status', OrganizationMembershipStatus::Active)
            ->where('is_owner', true)
            ->where('id', '!=', $membership->id)
            ->exists();
    }
}

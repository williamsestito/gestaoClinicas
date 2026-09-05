<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Define (ou redefine) o administrador de uma organização que ficou sem
 * nenhum owner ativo — ver Organization::scopeWithoutActiveOwner(). Mesma
 * regra de App\Actions\Organization\BootstrapOrganizationAction: um usuário
 * já existente recebe o vínculo imediatamente, um e-mail novo recebe um
 * convite (nunca senha definida por um administrador). Nunca desfaz vínculos
 * de outros usuários já existentes na organização.
 */
class SetOrganizationOwnerAction
{
    public function __construct(
        private readonly InviteUserAction $inviteUser,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(
        Organization $organization,
        User $actingPlatformAdmin,
        ?string $existingUserId,
        ?string $inviteEmail,
    ): void {
        if (($existingUserId === null) === ($inviteEmail === null)) {
            throw new InvalidArgumentException(
                'Informe exatamente um administrador: um usuário existente ou um convite por e-mail.',
            );
        }

        $ownerRole = Role::query()
            ->where('organization_id', $organization->id)
            ->where('slug', SystemRole::Owner->value)
            ->first();

        if ($existingUserId !== null) {
            $this->assignExistingUser($organization, $actingPlatformAdmin, $existingUserId, $ownerRole);

            return;
        }

        $headquarters = $organization->headquarters()->first();

        $this->inviteUser->handle(
            $organization,
            $actingPlatformAdmin,
            (string) $inviteEmail,
            $ownerRole,
            $headquarters ? [$headquarters] : [],
        );
    }

    private function assignExistingUser(
        Organization $organization,
        User $actingPlatformAdmin,
        string $userId,
        ?Role $ownerRole,
    ): void {
        $user = User::query()->findOrFail($userId);

        DB::transaction(function () use ($organization, $actingPlatformAdmin, $user, $ownerRole) {
            $membership = OrganizationMembership::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'user_id' => $user->id],
                [
                    'status' => OrganizationMembershipStatus::Active,
                    'is_owner' => true,
                    'role_id' => $ownerRole?->id,
                    'joined_at' => now(),
                    'created_by' => $actingPlatformAdmin->id,
                ],
            );

            $headquarters = $organization->headquarters()->first();

            if ($headquarters) {
                $membership->unitMemberships()->updateOrCreate(
                    ['unit_id' => $headquarters->id],
                    ['status' => RecordStatus::Active, 'is_manager' => true, 'is_primary' => true],
                );
            }

            $this->auditLogger->log(
                AuditAction::Updated,
                auditable: $membership,
                after: ['user_id' => $user->id, 'is_owner' => true],
                organization: $organization,
            );
        });
    }
}

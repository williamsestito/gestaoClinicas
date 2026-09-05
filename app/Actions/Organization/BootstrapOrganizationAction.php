<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Data\Organization\BootstrapOrganizationData;
use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Orquestra, em uma única transação, a criação completa da organização de
 * produção a partir do painel do platform admin: organização, entidade legal
 * principal, unidade matriz (com endereço), papéis de sistema e o vínculo do
 * primeiro administrador. Se qualquer etapa falhar, nada é persistido.
 *
 * Mesmo desenho de App\Actions\Organization\OnboardOrganizationAction (usado
 * pelo onboarding self-service), mas aqui quem cria não é o próprio
 * administrador: o platform admin nunca vira owner, e o administrador é
 * definido por um usuário já existente (vínculo imediato) ou por convite
 * (App\Actions\Organization\InviteUserAction — nunca senha definida por um
 * administrador, ver regra de segurança documentada nessa Action).
 */
class BootstrapOrganizationAction
{
    public function __construct(
        private readonly CreateOrganizationAction $createOrganization,
        private readonly CreateLegalEntityAction $createLegalEntity,
        private readonly CreateUnitAction $createUnit,
        private readonly SeedSystemRolesAction $seedSystemRoles,
        private readonly InviteUserAction $inviteUser,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(User $platformAdmin, BootstrapOrganizationData $data): Organization
    {
        [$organization, $ownerRole, $unit] = DB::transaction(function () use ($platformAdmin, $data) {
            $organization = $this->createOrganization->handle($data->organizationName);

            $legalEntity = $this->createLegalEntity->handle(
                organization: $organization,
                type: $data->legalEntityType,
                document: $data->document,
                legalName: $data->legalName,
                tradeName: $data->tradeName,
                address: $data->address,
                isPrimary: true,
            );

            $this->seedSystemRoles->handle($organization);

            $ownerRole = Role::query()
                ->where('organization_id', $organization->id)
                ->where('slug', SystemRole::Owner->value)
                ->first();

            $membership = $data->existingOwnerUserId !== null
                ? $this->createOwnerMembership($organization, $platformAdmin, $data->existingOwnerUserId, $ownerRole)
                : null;

            $unit = $this->createUnit->handle(
                organization: $organization,
                legalEntity: $legalEntity,
                name: $data->unitName,
                code: null,
                phone: $data->unitPhone,
                whatsapp: $data->unitWhatsapp,
                address: $data->address,
                openingHours: [],
                isHeadquarters: true,
                grantAccessTo: $membership,
            );

            return [$organization, $ownerRole, $unit];
        });

        if ($data->inviteEmail !== null) {
            $this->inviteUser->handle($organization, $platformAdmin, $data->inviteEmail, $ownerRole, [$unit]);
        }

        return $organization;
    }

    private function createOwnerMembership(
        Organization $organization,
        User $platformAdmin,
        string $ownerUserId,
        ?Role $ownerRole,
    ): OrganizationMembership {
        $owner = User::query()->findOrFail($ownerUserId);

        $membership = $organization->memberships()->create([
            'user_id' => $owner->id,
            'status' => OrganizationMembershipStatus::Active,
            'is_owner' => true,
            'role_id' => $ownerRole?->id,
            'joined_at' => now(),
            'created_by' => $platformAdmin->id,
        ]);

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $membership,
            after: ['user_id' => $owner->id, 'is_owner' => true],
            organization: $organization,
        );

        return $membership;
    }
}

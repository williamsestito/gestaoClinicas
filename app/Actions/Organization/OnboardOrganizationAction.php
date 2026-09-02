<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Data\Organization\OnboardOrganizationData;
use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Orquestra, em uma única transação, a criação completa de uma organização
 * a partir do onboarding: organização, entidade legal principal, unidade
 * matriz (com endereço e horários), papéis de sistema, vínculo de
 * proprietário e contexto ativo. Se qualquer etapa falhar, nada é
 * persistido.
 */
class OnboardOrganizationAction
{
    public function __construct(
        private readonly CreateOrganizationAction $createOrganization,
        private readonly CreateLegalEntityAction $createLegalEntity,
        private readonly CreateUnitAction $createUnit,
        private readonly SeedSystemRolesAction $seedSystemRoles,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(User $user, OnboardOrganizationData $data, Request $request): Organization
    {
        return DB::transaction(function () use ($user, $data, $request) {
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

            $membership = $organization->memberships()->create([
                'user_id' => $user->id,
                'status' => OrganizationMembershipStatus::Active,
                'is_owner' => true,
                'role_id' => $ownerRole?->id,
                'joined_at' => now(),
                'created_by' => $user->id,
            ]);

            $unit = $this->createUnit->handle(
                organization: $organization,
                legalEntity: $legalEntity,
                name: $data->unitName,
                code: null,
                phone: $data->unitPhone,
                whatsapp: $data->unitWhatsapp,
                address: $data->address,
                openingHours: $data->openingHours,
                isHeadquarters: true,
                grantAccessTo: $membership,
            );

            $this->auditLogger->log(
                AuditAction::Created,
                auditable: $membership,
                after: ['user_id' => $user->id, 'is_owner' => true],
                organization: $organization,
                unit: $unit,
            );

            $request->session()->put('active_organization_id', $organization->id);
            $request->session()->put('active_unit_id', $unit->id);

            return $organization;
        });
    }
}

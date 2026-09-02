<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Models\Organization;
use App\Models\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SetActiveOrganizationAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Request $request, User $user, Organization $organization): void
    {
        $membership = $user->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->where('status', OrganizationMembershipStatus::Active)
            ->first();

        if (! $membership && $user->is_platform_admin) {
            // Superadmin tem acesso global por decisão de produto — em vez
            // de ensinar todo o resto do sistema (policies, auditoria,
            // atribuição de unidade) a lidar com "sem vínculo", concede um
            // vínculo real (nunca proprietário) na primeira vez que ele
            // escolhe esta organização. Mesmo padrão manual que já existia
            // em DemoOrganizationSeeder::ensurePlatformAdminsHaveMembership,
            // generalizado para qualquer organização. Busca por
            // organization_id apenas (sem filtrar status) porque a unique
            // constraint é (organization_id, user_id) — se já existir um
            // vínculo inativo, reativa em vez de tentar criar um duplicado.
            $membership = $user->organizationMemberships()
                ->where('organization_id', $organization->id)
                ->first();

            if ($membership) {
                $membership->update(['status' => OrganizationMembershipStatus::Active]);
            } else {
                $membership = $user->organizationMemberships()->create([
                    'organization_id' => $organization->id,
                    'status' => OrganizationMembershipStatus::Active,
                    'is_owner' => false,
                    'joined_at' => now(),
                ]);
            }
        }

        if (! $membership) {
            throw ValidationException::withMessages([
                'organization' => 'Você não tem acesso a esta organização.',
            ]);
        }

        $request->session()->put('active_organization_id', $organization->id);
        $request->session()->forget('active_unit_id');

        $this->auditLogger->log(
            AuditAction::OrganizationContextSwitched,
            auditable: $organization,
            after: ['organization_id' => $organization->id],
            organization: $organization,
        );
    }
}

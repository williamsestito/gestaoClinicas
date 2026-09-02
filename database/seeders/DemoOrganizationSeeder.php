<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Organization\SeedSystemRolesAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\SystemRole;
use App\Models\Address;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Ambiente local de demonstração: garante uma organização com unidade
 * matriz, os 7 papéis de sistema, e os vínculos corretos dos dois
 * usuários de referência (ver docs — Bloco 11):
 *
 * - admin@admin.com: administrador técnico (is_platform_admin=true),
 *   também recebe um vínculo (sem ser proprietário) para poder usar o
 *   sistema operacional normalmente.
 * - admin@gestao-clinicas.local: administrador da clínica, proprietário
 *   da organização. NUNCA deve ser administrador técnico — se já existir
 *   com essa flag indevidamente marcada, este seeder a corrige.
 *
 * Idempotente e nunca sobrescreve senha de usuário já existente. Bloqueado
 * em produção.
 */
class DemoOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('DemoOrganizationSeeder bloqueado em produção.');

            return;
        }

        $organization = $this->ensureOrganization();
        $headquarters = $this->ensureHeadquarters($organization);

        app(SeedSystemRolesAction::class)->handle($organization);
        $ownerRole = Role::query()
            ->where('organization_id', $organization->id)
            ->where('slug', SystemRole::Owner->value)
            ->first();

        $this->ensureClinicAdmin($organization, $headquarters, $ownerRole);
        $this->ensurePlatformAdminsHaveMembership($organization, $headquarters);
    }

    private function ensureOrganization(): Organization
    {
        return Organization::query()->first() ?? Organization::factory()->create([
            'name' => 'Clínica Exemplo',
        ]);
    }

    private function ensureHeadquarters(Organization $organization): Unit
    {
        $headquarters = $organization->headquarters()->first();

        if ($headquarters) {
            return $headquarters;
        }

        $legalEntity = $organization->primaryLegalEntity()->first()
            ?? LegalEntity::factory()->primary()->for($organization)->create();

        $unit = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();

        Address::factory()->for($organization)->for($unit, 'addressable')->create();

        return $unit;
    }

    private function ensureClinicAdmin(Organization $organization, Unit $headquarters, ?Role $ownerRole): void
    {
        $email = (string) config('demo_environment.clinic_admin_email');
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $password = config('demo_environment.clinic_admin_password');

            if (blank($password)) {
                $this->command->warn(
                    "Defina DEMO_CLINIC_ADMIN_PASSWORD no .env para criar {$email} automaticamente. Nenhum usuário foi criado.",
                );

                return;
            }

            $user = new User([
                'name' => (string) config('demo_environment.clinic_admin_name'),
                'email' => $email,
                'password' => Hash::make((string) $password),
            ]);
            $user->forceFill([
                'is_active' => true,
                'is_platform_admin' => false,
                'email_verified_at' => now(),
            ])->save();

            $this->command->info("Administrador da clínica {$email} criado.");
        } elseif ($user->is_platform_admin) {
            // Corrige a inconsistência: este usuário nunca deveria ter sido
            // administrador técnico — não altera a senha existente.
            $user->forceFill(['is_platform_admin' => false])->save();
            $this->command->info("Corrigido: {$email} não é mais administrador técnico.");
        }

        $membership = OrganizationMembership::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $user->id],
            [
                'status' => OrganizationMembershipStatus::Active,
                'is_owner' => true,
                'role_id' => $ownerRole?->id,
                'joined_at' => now(),
                'created_by' => $user->id,
            ],
        );

        if ($membership->role_id === null && $ownerRole) {
            $membership->update(['role_id' => $ownerRole->id]);
        }

        UnitMembership::query()->firstOrCreate(
            ['organization_membership_id' => $membership->id, 'unit_id' => $headquarters->id],
            ['status' => RecordStatus::Active, 'is_manager' => true, 'is_primary' => true],
        );
    }

    private function ensurePlatformAdminsHaveMembership(Organization $organization, Unit $headquarters): void
    {
        foreach (User::query()->where('is_platform_admin', true)->get() as $user) {
            $membership = OrganizationMembership::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'user_id' => $user->id],
                [
                    'status' => OrganizationMembershipStatus::Active,
                    'is_owner' => false,
                    'joined_at' => now(),
                    'created_by' => $user->id,
                ],
            );

            UnitMembership::query()->firstOrCreate(
                ['organization_membership_id' => $membership->id, 'unit_id' => $headquarters->id],
                ['status' => RecordStatus::Active, 'is_manager' => false, 'is_primary' => true],
            );
        }
    }
}

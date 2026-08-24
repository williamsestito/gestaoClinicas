<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Os 7 papéis padrão criados automaticamente para toda organização
 * (ver RolesAndPermissionsSeeder). São papéis de sistema (`is_system`) —
 * não podem ser excluídos, mas nada impede o administrador de criar
 * papéis personalizados adicionais.
 */
enum SystemRole: string
{
    case Owner = 'proprietario';
    case ClinicAdmin = 'administrador-clinica';
    case UnitManager = 'gerente-unidade';
    case Reception = 'recepcao';
    case Professional = 'profissional';
    case Finance = 'financeiro';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Proprietário',
            self::ClinicAdmin => 'Administrador da clínica',
            self::UnitManager => 'Gerente de unidade',
            self::Reception => 'Recepção',
            self::Professional => 'Profissional',
            self::Finance => 'Financeiro',
            self::Auditor => 'Auditor',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Acesso total à clínica. Não pode ser removido enquanto for o único proprietário ativo.',
            self::ClinicAdmin => 'Gerencia a operação da clínica: unidades, entidades legais, usuários, site e SEO.',
            self::UnitManager => 'Gerencia a unidade sob sua responsabilidade.',
            self::Reception => 'Acesso operacional de recepção.',
            self::Professional => 'Acesso de profissional da clínica.',
            self::Finance => 'Acesso à área financeira da clínica.',
            self::Auditor => 'Acesso somente leitura para fins de auditoria.',
        };
    }

    /**
     * Permissões concedidas por padrão a este papel de sistema. O
     * proprietário não depende disto para a maior parte do sistema (tem
     * acesso total via `is_owner` em `PermissionChecker`), mas ainda
     * recebe o conjunto completo para consistência quando o papel é
     * exibido/reatribuído — **exceto** as duas permissões clínicas
     * (`MedicalRecordsManage`/`MedicalRecordsManageOwn`), deliberadamente
     * excluídas: `App\Policies\MedicalRecordPolicy` é a única Policy do
     * sistema que nunca usa o atalho de `is_owner`/`is_platform_admin` e
     * consulta a permissão real do papel (RN-015/RN-016) — se essas duas
     * chaves estivessem aqui, todo proprietário teria acesso clínico
     * irrestrito de verdade (não um bypass, uma permissão de fato
     * concedida), o mesmo problema que RN-016 proíbe.
     *
     * @return array<int, PermissionKey>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Owner => array_values(array_filter(
                PermissionKey::cases(),
                fn (PermissionKey $key): bool => ! in_array($key, [
                    PermissionKey::MedicalRecordsManage,
                    PermissionKey::MedicalRecordsManageOwn,
                ], true),
            )),

            self::ClinicAdmin => [
                PermissionKey::DashboardView,
                PermissionKey::OrganizationView,
                PermissionKey::OrganizationUpdate,
                PermissionKey::UnitsView,
                PermissionKey::UnitsCreate,
                PermissionKey::UnitsUpdate,
                PermissionKey::UnitsActivate,
                PermissionKey::UnitsDeactivate,
                PermissionKey::UnitsDelete,
                PermissionKey::UnitsRestore,
                PermissionKey::UnitsSetHeadquarters,
                PermissionKey::LegalEntitiesView,
                PermissionKey::LegalEntitiesCreate,
                PermissionKey::LegalEntitiesUpdate,
                PermissionKey::LegalEntitiesDelete,
                PermissionKey::LegalEntitiesRestore,
                PermissionKey::LegalEntitiesSetPrimary,
                PermissionKey::UsersView,
                PermissionKey::UsersCreate,
                PermissionKey::UsersInvite,
                PermissionKey::UsersUpdate,
                PermissionKey::UsersActivate,
                PermissionKey::UsersDeactivate,
                PermissionKey::UsersAssignRoles,
                PermissionKey::UsersAssignUnits,
                PermissionKey::RolesView,
                PermissionKey::RolesCreate,
                PermissionKey::RolesUpdate,
                PermissionKey::RolesDelete,
                PermissionKey::RolesAssignPermissions,
                PermissionKey::SiteView,
                PermissionKey::SiteUpdate,
                PermissionKey::SitePublish,
                PermissionKey::SiteAppointmentsView,
                PermissionKey::SiteAppointmentsManage,
                PermissionKey::SeoView,
                PermissionKey::SeoUpdate,
                PermissionKey::AuditView,
                PermissionKey::SettingsView,
                PermissionKey::SettingsUpdate,
                PermissionKey::ModulesView,
                PermissionKey::ModulesManage,
                PermissionKey::SpecialtiesView,
                PermissionKey::SpecialtiesManage,
                PermissionKey::ServicesView,
                PermissionKey::ServicesManage,
                PermissionKey::ResourcesView,
                PermissionKey::ResourcesManage,
                PermissionKey::PatientsView,
                PermissionKey::PatientsManage,
                PermissionKey::AppointmentsView,
                PermissionKey::AppointmentsManage,
                PermissionKey::ProfessionalsView,
                PermissionKey::ProfessionalsManage,
                PermissionKey::ProfessionalsManageSpecialties,
                PermissionKey::ProfessionalsManageUnits,
                PermissionKey::ProfessionalsManageServices,
                PermissionKey::ProfessionalRegistrationsView,
                PermissionKey::ProfessionalRegistrationsManage,
                PermissionKey::ProfessionalRegistrationsViewSensitive,
                PermissionKey::ProfessionalAvailabilityView,
                PermissionKey::ProfessionalAvailabilityManage,
                PermissionKey::ProfessionalTimeBlocksView,
                PermissionKey::ProfessionalTimeBlocksManage,
                PermissionKey::ProductsView,
                PermissionKey::ProductsManage,
                PermissionKey::SalesView,
                PermissionKey::SalesManage,
                PermissionKey::SalesApproveDiscount,
            ],

            // Gerente de unidade recebe as permissões de gestão de jornada/
            // bloqueios, mas fica restrito às unidades em que possui
            // UnitMembership com is_manager=true — ver
            // App\Policies\ProfessionalPolicy::manageAvailability()/manageTimeBlocks().
            self::UnitManager => [
                PermissionKey::DashboardView,
                PermissionKey::UnitsView,
                PermissionKey::UnitsUpdate,
                PermissionKey::UnitsActivate,
                PermissionKey::UnitsDeactivate,
                PermissionKey::LegalEntitiesView,
                PermissionKey::UsersView,
                PermissionKey::SiteView,
                PermissionKey::SiteAppointmentsView,
                PermissionKey::SeoView,
                PermissionKey::AuditView,
                PermissionKey::SettingsView,
                PermissionKey::ModulesView,
                PermissionKey::SpecialtiesView,
                PermissionKey::ServicesView,
                PermissionKey::ResourcesView,
                PermissionKey::PatientsView,
                PermissionKey::AppointmentsView,
                PermissionKey::ProfessionalsView,
                PermissionKey::ProfessionalRegistrationsView,
                PermissionKey::ProfessionalAvailabilityView,
                PermissionKey::ProfessionalAvailabilityManage,
                PermissionKey::ProfessionalTimeBlocksView,
                PermissionKey::ProfessionalTimeBlocksManage,
                PermissionKey::ProductsView,
                PermissionKey::SalesView,
                PermissionKey::SalesManage,
                PermissionKey::SalesApproveDiscount,
            ],

            self::Reception => [
                PermissionKey::DashboardView,
                PermissionKey::UnitsView,
                PermissionKey::LegalEntitiesView,
                PermissionKey::SiteAppointmentsView,
                PermissionKey::SiteAppointmentsManage,
                PermissionKey::PatientsView,
                PermissionKey::PatientsManage,
                PermissionKey::AppointmentsView,
                PermissionKey::AppointmentsManage,
                PermissionKey::SettingsView,
                PermissionKey::ProductsView,
                PermissionKey::SalesView,
                PermissionKey::SalesManage,
            ],

            self::Professional => [
                PermissionKey::DashboardView,
                PermissionKey::SettingsView,
                PermissionKey::PatientsViewOwn,
                PermissionKey::AppointmentsViewOwn,
                PermissionKey::AppointmentsManageOwn,
                PermissionKey::MedicalRecordsManageOwn,
                PermissionKey::ProfessionalOwnAvailabilityView,
                PermissionKey::ProfessionalOwnAvailabilityManage,
                PermissionKey::ProfessionalOwnTimeBlocksView,
                PermissionKey::ProfessionalOwnTimeBlocksManage,
                PermissionKey::ProductsView,
                PermissionKey::SalesManageOwn,
            ],

            self::Finance => [
                PermissionKey::DashboardView,
                PermissionKey::UnitsView,
                PermissionKey::LegalEntitiesView,
                PermissionKey::SettingsView,
                PermissionKey::ProductsView,
                PermissionKey::SalesView,
            ],

            self::Auditor => [
                PermissionKey::DashboardView,
                PermissionKey::UnitsView,
                PermissionKey::LegalEntitiesView,
                PermissionKey::UsersView,
                PermissionKey::SiteView,
                PermissionKey::SiteAppointmentsView,
                PermissionKey::SeoView,
                PermissionKey::AuditView,
                PermissionKey::SettingsView,
                PermissionKey::ModulesView,
                PermissionKey::SpecialtiesView,
                PermissionKey::ServicesView,
                PermissionKey::ResourcesView,
                PermissionKey::PatientsView,
                PermissionKey::AppointmentsView,
                PermissionKey::ProfessionalsView,
                PermissionKey::ProfessionalRegistrationsView,
                PermissionKey::ProfessionalRegistrationsViewSensitive,
                PermissionKey::ProfessionalAvailabilityView,
                PermissionKey::ProfessionalTimeBlocksView,
                PermissionKey::ProductsView,
                PermissionKey::SalesView,
            ],
        };
    }
}

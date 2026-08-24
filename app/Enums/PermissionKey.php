<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Catálogo fechado de permissões reais do sistema. Toda checagem de
 * autorização baseada em papel deve referenciar um destes casos — nunca
 * uma string solta. O proprietário e o administrador técnico sempre têm
 * acesso total independentemente do que está aqui (ver PermissionChecker).
 */
enum PermissionKey: string
{
    case DashboardView = 'dashboard.view';

    case OrganizationView = 'organization.view';
    case OrganizationUpdate = 'organization.update';

    case UnitsView = 'units.view';
    case UnitsCreate = 'units.create';
    case UnitsUpdate = 'units.update';
    case UnitsActivate = 'units.activate';
    case UnitsDeactivate = 'units.deactivate';
    case UnitsDelete = 'units.delete';
    case UnitsRestore = 'units.restore';
    case UnitsSetHeadquarters = 'units.set-headquarters';

    case LegalEntitiesView = 'legal-entities.view';
    case LegalEntitiesCreate = 'legal-entities.create';
    case LegalEntitiesUpdate = 'legal-entities.update';
    case LegalEntitiesDelete = 'legal-entities.delete';
    case LegalEntitiesRestore = 'legal-entities.restore';
    case LegalEntitiesSetPrimary = 'legal-entities.set-primary';

    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersInvite = 'users.invite';
    case UsersUpdate = 'users.update';
    case UsersActivate = 'users.activate';
    case UsersDeactivate = 'users.deactivate';
    case UsersAssignRoles = 'users.assign-roles';
    case UsersAssignUnits = 'users.assign-units';

    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';
    case RolesAssignPermissions = 'roles.assign-permissions';

    case SiteView = 'site.view';
    case SiteUpdate = 'site.update';
    case SitePublish = 'site.publish';
    case SiteAppointmentsView = 'site.appointments.view';
    case SiteAppointmentsManage = 'site.appointments.manage';

    case SeoView = 'seo.view';
    case SeoUpdate = 'seo.update';

    case AuditView = 'audit.view';

    case SettingsView = 'settings.view';
    case SettingsUpdate = 'settings.update';

    case ModulesView = 'modules.view';
    case ModulesManage = 'modules.manage';

    case SpecialtiesView = 'specialties.view';
    case SpecialtiesManage = 'specialties.manage';

    case ServicesView = 'services.view';
    case ServicesManage = 'services.manage';

    case ResourcesView = 'resources.view';
    case ResourcesManage = 'resources.manage';

    case PatientsView = 'patients.view';
    case PatientsManage = 'patients.manage';
    case PatientsViewOwn = 'patients.view-own';

    case AppointmentsView = 'appointments.view';
    case AppointmentsManage = 'appointments.manage';
    case AppointmentsViewOwn = 'appointments.view-own';
    case AppointmentsManageOwn = 'appointments.manage-own';

    /**
     * Conteúdo clínico (prontuário) — nunca concedida a proprietário/admin da
     * plataforma automaticamente (RN-015/RN-016 do documento de visão:
     * "administrador da plataforma"/"proprietário administrativo não possui
     * acesso clínico automático"). `MedicalRecordPolicy` por isso NÃO usa
     * `PermissionChecker::can()` para `MedicalRecordsManage` — esse método
     * sempre libera owner/platform-admin (ver seu próprio docblock), o que
     * violaria as duas regras. Concessão só via papel customizado que a
     * própria clínica cria e atribui explicitamente (ex.: "Responsável
     * técnico").
     */
    case MedicalRecordsManage = 'medical-records.manage';
    case MedicalRecordsManageOwn = 'medical-records.manage-own';

    case ProfessionalsView = 'professionals.view';
    case ProfessionalsManage = 'professionals.manage';
    case ProfessionalsManageSpecialties = 'professionals.manage-specialties';
    case ProfessionalsManageUnits = 'professionals.manage-units';
    case ProfessionalsManageServices = 'professionals.manage-services';

    case ProfessionalRegistrationsView = 'professional-registrations.view';
    case ProfessionalRegistrationsManage = 'professional-registrations.manage';
    case ProfessionalRegistrationsViewSensitive = 'professional-registrations.view-sensitive';

    case ProfessionalAvailabilityView = 'professional-availability.view';
    case ProfessionalAvailabilityManage = 'professional-availability.manage';
    case ProfessionalTimeBlocksView = 'professional-time-blocks.view';
    case ProfessionalTimeBlocksManage = 'professional-time-blocks.manage';

    case ProfessionalOwnAvailabilityView = 'professional-availability.view-own';
    case ProfessionalOwnAvailabilityManage = 'professional-availability.manage-own';
    case ProfessionalOwnTimeBlocksView = 'professional-time-blocks.view-own';
    case ProfessionalOwnTimeBlocksManage = 'professional-time-blocks.manage-own';

    case ProductsView = 'products.view';
    case ProductsManage = 'products.manage';

    case SalesView = 'sales.view';
    case SalesManage = 'sales.manage';
    case SalesManageOwn = 'sales.manage-own';
    case SalesApproveDiscount = 'sales.approve-discount';

    public function label(): string
    {
        return match ($this) {
            self::DashboardView => 'Visualizar o painel geral',

            self::OrganizationView => 'Visualizar dados da clínica',
            self::OrganizationUpdate => 'Editar dados da clínica',

            self::UnitsView => 'Visualizar unidades',
            self::UnitsCreate => 'Criar unidades',
            self::UnitsUpdate => 'Editar unidades',
            self::UnitsActivate => 'Ativar unidades',
            self::UnitsDeactivate => 'Inativar unidades',
            self::UnitsDelete => 'Excluir unidades',
            self::UnitsRestore => 'Restaurar unidades',
            self::UnitsSetHeadquarters => 'Definir unidade matriz',

            self::LegalEntitiesView => 'Visualizar entidades legais',
            self::LegalEntitiesCreate => 'Criar entidades legais',
            self::LegalEntitiesUpdate => 'Editar entidades legais',
            self::LegalEntitiesDelete => 'Excluir entidades legais',
            self::LegalEntitiesRestore => 'Restaurar entidades legais',
            self::LegalEntitiesSetPrimary => 'Definir entidade legal principal',

            self::UsersView => 'Visualizar usuários',
            self::UsersCreate => 'Cadastrar usuários',
            self::UsersInvite => 'Convidar usuários',
            self::UsersUpdate => 'Editar usuários',
            self::UsersActivate => 'Ativar usuários',
            self::UsersDeactivate => 'Inativar usuários',
            self::UsersAssignRoles => 'Atribuir papéis a usuários',
            self::UsersAssignUnits => 'Atribuir unidades a usuários',

            self::RolesView => 'Visualizar papéis e permissões',
            self::RolesCreate => 'Criar papéis personalizados',
            self::RolesUpdate => 'Editar papéis personalizados',
            self::RolesDelete => 'Excluir papéis personalizados',
            self::RolesAssignPermissions => 'Atribuir permissões a papéis',

            self::SiteView => 'Visualizar o site da clínica',
            self::SiteUpdate => 'Editar o site da clínica',
            self::SitePublish => 'Publicar ou despublicar o site',
            self::SiteAppointmentsView => 'Visualizar solicitações de agendamento',
            self::SiteAppointmentsManage => 'Gerenciar solicitações de agendamento',

            self::SeoView => 'Visualizar SEO e marketing',
            self::SeoUpdate => 'Editar SEO e marketing',

            self::AuditView => 'Visualizar auditoria',

            self::SettingsView => 'Visualizar configurações',
            self::SettingsUpdate => 'Editar configurações',

            self::ModulesView => 'Visualizar módulos de especialidade',
            self::ModulesManage => 'Habilitar ou desabilitar módulos de especialidade',

            self::SpecialtiesView => 'Visualizar especialidades',
            self::SpecialtiesManage => 'Gerenciar especialidades',

            self::ServicesView => 'Visualizar serviços',
            self::ServicesManage => 'Gerenciar serviços',
            self::ResourcesView => 'Visualizar recursos (salas/equipamentos)',
            self::ResourcesManage => 'Gerenciar recursos (salas/equipamentos)',

            self::PatientsView => 'Visualizar pacientes',
            self::PatientsManage => 'Gerenciar pacientes',
            self::PatientsViewOwn => 'Visualizar os próprios pacientes vinculados',

            self::AppointmentsView => 'Visualizar agendamentos',
            self::AppointmentsManage => 'Gerenciar agendamentos',
            self::AppointmentsViewOwn => 'Visualizar os próprios atendimentos',
            self::AppointmentsManageOwn => 'Gerenciar os próprios atendimentos (confirmar, check-in, início, conclusão, reagendar, cancelar, converter pré-agendamento)',

            self::MedicalRecordsManage => 'Gerenciar prontuários de qualquer paciente (acesso clínico amplo)',
            self::MedicalRecordsManageOwn => 'Gerenciar prontuários dos próprios atendimentos',

            self::ProfessionalsView => 'Visualizar profissionais',
            self::ProfessionalsManage => 'Gerenciar profissionais',
            self::ProfessionalsManageSpecialties => 'Gerenciar especialidades do profissional',
            self::ProfessionalsManageUnits => 'Gerenciar vínculos de profissionais com unidades',
            self::ProfessionalsManageServices => 'Gerenciar vínculos de profissionais com serviços',

            self::ProfessionalRegistrationsView => 'Visualizar registros profissionais',
            self::ProfessionalRegistrationsManage => 'Gerenciar registros profissionais',
            self::ProfessionalRegistrationsViewSensitive => 'Visualizar número completo de registros profissionais',

            self::ProfessionalAvailabilityView => 'Visualizar jornada e disponibilidade',
            self::ProfessionalAvailabilityManage => 'Gerenciar jornada e disponibilidade',
            self::ProfessionalTimeBlocksView => 'Visualizar ausências e bloqueios',
            self::ProfessionalTimeBlocksManage => 'Gerenciar ausências e bloqueios',
            self::ProfessionalOwnAvailabilityView => 'Visualizar a própria jornada e disponibilidade',
            self::ProfessionalOwnAvailabilityManage => 'Gerenciar a própria jornada e disponibilidade',
            self::ProfessionalOwnTimeBlocksView => 'Visualizar as próprias ausências e bloqueios',
            self::ProfessionalOwnTimeBlocksManage => 'Gerenciar as próprias ausências e bloqueios',

            self::ProductsView => 'Visualizar produtos',
            self::ProductsManage => 'Gerenciar produtos',

            self::SalesView => 'Visualizar vendas',
            self::SalesManage => 'Gerenciar vendas de qualquer paciente',
            self::SalesManageOwn => 'Gerenciar vendas dos próprios pacientes',
            self::SalesApproveDiscount => 'Aprovar desconto acima do limite',
        };
    }

    /** Grupo usado para organizar a tela de "Perfis e permissões" por seção. */
    public function group(): string
    {
        return match (true) {
            $this === self::DashboardView => 'Visão geral',
            str_starts_with($this->value, 'organization.') => 'Dados da clínica',
            str_starts_with($this->value, 'units.') => 'Unidades',
            str_starts_with($this->value, 'legal-entities.') => 'Entidades legais',
            str_starts_with($this->value, 'users.') => 'Usuários',
            str_starts_with($this->value, 'roles.') => 'Papéis e permissões',
            str_starts_with($this->value, 'site.') => 'Site da clínica',
            str_starts_with($this->value, 'seo.') => 'SEO e marketing',
            $this === self::AuditView => 'Auditoria',
            str_starts_with($this->value, 'settings.') => 'Configurações',
            str_starts_with($this->value, 'modules.') => 'Módulos de especialidade',
            str_starts_with($this->value, 'specialties.') => 'Especialidades',
            str_starts_with($this->value, 'services.') => 'Serviços',
            str_starts_with($this->value, 'resources.') => 'Recursos',
            str_starts_with($this->value, 'patients.') => 'Pacientes',
            str_starts_with($this->value, 'appointments.') => 'Agendamentos',
            str_starts_with($this->value, 'medical-records.') => 'Prontuário',
            str_starts_with($this->value, 'professional-registrations.') => 'Registros profissionais',
            str_starts_with($this->value, 'professional-availability.') => 'Jornada e disponibilidade',
            str_starts_with($this->value, 'professional-time-blocks.') => 'Ausências e bloqueios',
            str_starts_with($this->value, 'professionals.') => 'Profissionais',
            str_starts_with($this->value, 'products.') => 'Produtos',
            str_starts_with($this->value, 'sales.') => 'Vendas',
            default => 'Geral',
        };
    }
}

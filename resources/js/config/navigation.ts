import {
    Blocks,
    Briefcase,
    Building2,
    CalendarCheck,
    CalendarClock,
    CalendarOff,
    Contact,
    DoorOpen,
    FileText,
    Globe,
    History,
    IdCard,
    Inbox,
    LayoutGrid,
    MapPin,
    Package,
    Search,
    ShieldCheck,
    ShoppingCart,
    Stethoscope,
    UserRound,
    Users,
} from '@lucide/vue';
import { dashboard } from '@/routes';
import { index as indexAppointments } from '@/routes/settings/appointments';
import { index as indexAudit } from '@/routes/settings/audit';
import { index as indexLegalEntities } from '@/routes/settings/legal-entities';
import { edit as editModules } from '@/routes/settings/modules';
import { index as indexMyAppointmentRequests } from '@/routes/settings/my-appointment-requests';
import { index as indexMyPatients } from '@/routes/settings/my-patients';
import {
    availability as myAvailability,
    profile as myProfile,
    timeBlocks as myTimeBlocks,
} from '@/routes/settings/my-schedule';
import { edit as editOrganization } from '@/routes/settings/organization';
import { index as indexPatients } from '@/routes/settings/patients';
import { index as indexProducts } from '@/routes/settings/products';
import { index as indexProfessionals } from '@/routes/settings/professionals';
import { index as indexResources } from '@/routes/settings/resources';
import { index as indexRoles } from '@/routes/settings/roles';
import { index as indexSales } from '@/routes/settings/sales';
import { edit as editSeo } from '@/routes/settings/seo';
import { index as indexServices } from '@/routes/settings/services';
import { edit as editSite } from '@/routes/settings/site';
import { index as indexSpecialties } from '@/routes/settings/specialties';
import { index as indexUnits } from '@/routes/settings/units';
import { index as indexUsers } from '@/routes/settings/users';
import type { NavGroup } from '@/types';

/**
 * Fonte única da navegação principal. Cada item pode declarar uma
 * `permission` — só é exibido se a chave estiver em `tenant.permissions`
 * (ver PermissionChecker no backend). Isso é só reflexo de UI: a
 * autorização real de cada rota é sempre revalidada no backend.
 */
export function buildNavGroups(permissions: string[]): NavGroup[] {
    const can = (permission: string) => permissions.includes(permission);

    const groups: NavGroup[] = [
        {
            title: 'Visão geral',
            items: [
                {
                    title: 'Dashboard',
                    href: dashboard(),
                    icon: LayoutGrid,
                    permission: 'dashboard.view',
                },
            ],
        },
        {
            title: 'Gestão da clínica',
            items: [
                {
                    title: 'Dados da clínica',
                    href: editOrganization(),
                    icon: Building2,
                    permission: 'organization.view',
                },
                {
                    title: 'Unidades',
                    href: indexUnits(),
                    icon: MapPin,
                    permission: 'units.view',
                },
                {
                    title: 'Entidades legais',
                    href: indexLegalEntities(),
                    icon: FileText,
                    permission: 'legal-entities.view',
                },
                {
                    title: 'Módulos',
                    href: editModules(),
                    icon: Blocks,
                    permission: 'modules.view',
                },
            ],
        },
        {
            title: 'Área profissional',
            items: [
                {
                    title: 'Meus dados',
                    href: myProfile(),
                    icon: IdCard,
                    permission: 'professional-availability.view-own',
                },
                {
                    title: 'Minha agenda',
                    href: myAvailability(),
                    icon: CalendarClock,
                    permission: 'professional-availability.view-own',
                },
                {
                    title: 'Minhas ausências',
                    href: myTimeBlocks(),
                    icon: CalendarOff,
                    permission: 'professional-time-blocks.view-own',
                },
                {
                    title: 'Meus pacientes',
                    href: indexMyPatients(),
                    icon: Contact,
                    permission: 'patients.view-own',
                },
                {
                    title: 'Meus pré-agendamentos',
                    href: indexMyAppointmentRequests(),
                    icon: Inbox,
                    permission: 'professional-availability.view-own',
                },
            ],
        },
        {
            title: 'Pacientes',
            items: [
                {
                    title: 'Pacientes',
                    href: indexPatients(),
                    icon: Contact,
                    permission: 'patients.view',
                },
            ],
        },
        {
            title: 'Agenda',
            items: [
                {
                    title: 'Agenda',
                    href: indexAppointments(),
                    icon: CalendarCheck,
                    permission: 'appointments.view',
                },
            ],
        },
        {
            title: 'Equipe e serviços',
            items: [
                {
                    title: 'Profissionais',
                    href: indexProfessionals(),
                    icon: UserRound,
                    permission: 'professionals.view',
                },
                {
                    title: 'Especialidades',
                    href: indexSpecialties(),
                    icon: Stethoscope,
                    permission: 'specialties.view',
                },
                {
                    title: 'Serviços e procedimentos',
                    href: indexServices(),
                    icon: Briefcase,
                    permission: 'services.view',
                },
                {
                    title: 'Recursos',
                    href: indexResources(),
                    icon: DoorOpen,
                    permission: 'resources.view',
                },
            ],
        },
        {
            title: 'Comercial',
            items: [
                {
                    title: 'Produtos',
                    href: indexProducts(),
                    icon: Package,
                    permission: 'products.view',
                },
                {
                    title: 'Vendas',
                    href: indexSales(),
                    icon: ShoppingCart,
                    permission: 'sales.view',
                },
            ],
        },
        {
            title: 'Equipe e acessos',
            items: [
                {
                    title: 'Usuários',
                    href: indexUsers(),
                    icon: Users,
                    permission: 'users.view',
                },
                {
                    title: 'Perfis e permissões',
                    href: indexRoles(),
                    icon: ShieldCheck,
                    permission: 'roles.view',
                },
            ],
        },
        {
            title: 'Presença digital',
            items: [
                {
                    title: 'Site da clínica',
                    href: editSite(),
                    icon: Globe,
                    permission: 'site.view',
                },
                {
                    title: 'SEO e marketing',
                    href: editSeo(),
                    icon: Search,
                    permission: 'seo.view',
                },
            ],
        },
        {
            title: 'Sistema',
            items: [
                {
                    title: 'Auditoria',
                    href: indexAudit(),
                    icon: History,
                    permission: 'audit.view',
                },
            ],
        },
    ];

    return groups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => !item.permission || can(item.permission),
            ),
        }))
        .filter((group) => group.items.length > 0);
}

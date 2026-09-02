import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Dashboard from './Dashboard.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>' },
    router: { get: vi.fn(), delete: vi.fn() },
    useForm: (initial: Record<string, unknown>) => ({
        ...initial,
        errors: {},
        processing: false,
        post: vi.fn(),
        reset: vi.fn(),
    }),
    usePage: () => ({
        props: {
            tenant: {
                organization: {
                    id: '1',
                    name: 'Clínica Exemplo',
                    status: 'active',
                },
                unit: { id: '1', name: 'Matriz' },
                isOwner: false,
                membership: { role_name: 'Profissional' },
            },
        },
    }),
}));

const baseAdminProps = {
    organizationName: 'Clínica Exemplo',
    unitsCount: 1,
    usersCount: 1,
    activeUsersCount: 1,
    inactiveUsersCount: 0,
    legalEntitiesCount: 1,
    primaryLegalEntity: null,
    domainConfigured: false,
    seoConfigured: false,
    recentActivity: [],
    pendingSetupItems: [],
    pendingAppointmentRequestsByProfessional: null,
    orgAgenda: null,
};

const professionalDashboard = {
    period: 'day' as const,
    referenceDate: '2026-08-17',
    rangeLabel: '17/08/2026',
    counters: { open: 1, scheduled: 2, completed: 3 },
    agenda: [],
    agendaTruncated: false,
    completedWithoutMedicalRecordCount: 0,
    pendingAppointmentRequestsCount: 0,
    pendingAppointmentRequests: [],
    reminders: [],
};

describe('Dashboard', () => {
    it('renders the professional dashboard when professionalDashboard is provided', () => {
        const wrapper = mount(Dashboard, {
            props: { ...baseAdminProps, professionalDashboard },
        });

        expect(wrapper.text()).toContain('Em aberto');
        expect(wrapper.text()).not.toContain('Últimas atividades');
        expect(wrapper.text()).not.toContain('Atalhos');
    });

    it('renders the admin dashboard when professionalDashboard is null', () => {
        const wrapper = mount(Dashboard, {
            props: { ...baseAdminProps, professionalDashboard: null },
        });

        expect(wrapper.text()).toContain('Atalhos');
        expect(wrapper.text()).not.toContain('Em aberto');
    });

    it('hides the pending-appointment-request alert when there is nothing to show', () => {
        const wrapper = mount(Dashboard, {
            props: { ...baseAdminProps, professionalDashboard: null },
        });

        expect(wrapper.text()).not.toContain(
            'Pré-agendamentos aguardando confirmação',
        );
    });

    it('shows the pending-appointment-request alert grouped by professional, for admin/atendimento', () => {
        const wrapper = mount(Dashboard, {
            props: {
                ...baseAdminProps,
                professionalDashboard: null,
                pendingAppointmentRequestsByProfessional: [
                    {
                        professional_id: 'prof-1',
                        professional_name: 'Dra Juliana Cruz',
                        count: 2,
                        requests: [
                            {
                                id: 'req-1',
                                name: 'Ana Souza',
                                phone: '(47) 99999-0000',
                                service_name: 'Consulta',
                                created_at: '2026-08-17T10:00:00Z',
                            },
                        ],
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain(
            'Pré-agendamentos aguardando confirmação',
        );
        expect(wrapper.text()).toContain('Dra Juliana Cruz');
        expect(wrapper.text()).toContain('Ana Souza');

        const link = wrapper
            .findAll('a')
            .find((a) => a.text() === 'Ver e confirmar');
        expect(link?.attributes('href')).toContain('professional_id=prof-1');
    });

    it('shows the organization agenda card with a professional filter when orgAgenda is provided', () => {
        const wrapper = mount(Dashboard, {
            props: {
                ...baseAdminProps,
                professionalDashboard: null,
                orgAgenda: {
                    date: '2026-08-17',
                    professionalId: null,
                    professionals: [
                        { id: 'prof-1', display_name: 'Dra Juliana Cruz' },
                    ],
                    appointments: [
                        {
                            id: 'apt-1',
                            starts_at: '2026-08-17T12:00:00Z',
                            ends_at: '2026-08-17T12:30:00Z',
                            status: 'confirmed',
                            status_label: 'Confirmado',
                            professional_name: 'Dra Juliana Cruz',
                            patient_name: 'Ana Souza',
                            service_name: 'Consulta',
                            unit_name: 'Matriz',
                        },
                    ],
                },
            },
        });

        expect(wrapper.text()).toContain('Ana Souza');
        expect(wrapper.text()).toContain('Dra Juliana Cruz');

        const options = wrapper
            .find('select')
            .findAll('option')
            .map((option) => option.text());
        expect(options).toEqual([
            'Todos os profissionais',
            'Dra Juliana Cruz',
        ]);
    });

    it('hides the organization agenda card when orgAgenda is null', () => {
        const wrapper = mount(Dashboard, {
            props: { ...baseAdminProps, professionalDashboard: null },
        });

        expect(wrapper.text()).not.toContain('Agendamentos da clínica');
    });
});

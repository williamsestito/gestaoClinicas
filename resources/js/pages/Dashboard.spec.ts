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
    pendingSetupItems: [],
    pendingAppointmentRequestsByProfessional: null,
    orgAgenda: null,
    indicators: null,
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
        expect(options).toEqual(['Todos os profissionais', 'Dra Juliana Cruz']);
    });

    it('hides the organization agenda card when orgAgenda is null', () => {
        const wrapper = mount(Dashboard, {
            props: { ...baseAdminProps, professionalDashboard: null },
        });

        expect(wrapper.text()).not.toContain('Agendamentos da clínica');
    });

    it('no longer shows a technical activity/audit log feed on the main dashboard', () => {
        const wrapper = mount(Dashboard, {
            props: { ...baseAdminProps, professionalDashboard: null },
        });

        expect(wrapper.text()).not.toContain('Últimas atividades');
    });

    it('shows the management indicator cards and charts when indicators are provided', () => {
        const wrapper = mount(Dashboard, {
            props: {
                ...baseAdminProps,
                professionalDashboard: null,
                indicators: {
                    todayAppointmentsCount: 4,
                    pendingConfirmationsCount: 2,
                    revenueThisMonthCents: 150000,
                    newPatientsThisMonthCount: 3,
                    charts: {
                        appointmentsByWeekday: [
                            { label: 'Seg', count: 5 },
                            { label: 'Ter', count: 3 },
                        ],
                        revenueByMonth: [
                            { label: 'Jan/26', total_cents: 100000 },
                            { label: 'Fev/26', total_cents: 150000 },
                        ],
                        occupancyByProfessional: [
                            { label: 'Dra Juliana Cruz', count: 10 },
                        ],
                    },
                },
            },
        });

        // formatCurrencyBrl usa toLocaleString, que insere um espaço não
        // separável (U+00A0) entre "R$" e o valor — normaliza antes de
        // comparar para não depender desse detalhe do Intl (mesmo padrão de
        // settings/sales/Create.spec.ts).
        const normalized = wrapper.text().replace(/ /g, ' ');

        expect(normalized).toContain('Agendamentos hoje');
        expect(normalized).toContain('4');
        expect(normalized).toContain('Confirmações pendentes');
        expect(normalized).toContain('Faturamento no mês');
        expect(normalized).toContain('R$ 1.500,00');
        expect(normalized).toContain('Novos pacientes no mês');

        expect(wrapper.text()).toContain('Agendamentos por dia da semana');
        expect(wrapper.text()).toContain('Faturamento por período');
        expect(wrapper.text()).toContain('Ocupação por profissional');
        expect(wrapper.text()).toContain('Dra Juliana Cruz');
    });

    it('does not show indicator cards for sections the user has no permission to see', () => {
        const wrapper = mount(Dashboard, {
            props: {
                ...baseAdminProps,
                professionalDashboard: null,
                indicators: {
                    todayAppointmentsCount: 4,
                    pendingConfirmationsCount: null,
                    revenueThisMonthCents: null,
                    newPatientsThisMonthCount: null,
                    charts: {
                        appointmentsByWeekday: [{ label: 'Seg', count: 5 }],
                        revenueByMonth: null,
                        occupancyByProfessional: null,
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Agendamentos hoje');
        expect(wrapper.text()).not.toContain('Confirmações pendentes');
        expect(wrapper.text()).not.toContain('Faturamento no mês');
        expect(wrapper.text()).not.toContain('Novos pacientes no mês');
        expect(wrapper.text()).not.toContain('Faturamento por período');
        expect(wrapper.text()).not.toContain('Ocupação por profissional');
    });
});

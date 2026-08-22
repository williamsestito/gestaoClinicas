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
};

const professionalDashboard = {
    period: 'day' as const,
    referenceDate: '2026-08-17',
    rangeLabel: '17/08/2026',
    counters: { open: 1, scheduled: 2, completed: 3 },
    agenda: [],
    agendaTruncated: false,
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
});

import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ProfessionalDashboard from './ProfessionalDashboard.vue';

const { getMock, deleteMock, patchMock, postMock, resetMock } = vi.hoisted(
    () => ({
        getMock: vi.fn(),
        deleteMock: vi.fn(),
        patchMock: vi.fn(),
        postMock: vi.fn(),
        resetMock: vi.fn(),
    }),
);

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>' },
    router: { get: getMock, delete: deleteMock, patch: patchMock },
    useForm: (initial: Record<string, unknown>) => ({
        ...initial,
        errors: {},
        processing: false,
        post: postMock,
        reset: resetMock,
    }),
}));

function makeData(overrides: Partial<typeof baseData> = {}) {
    return { ...baseData, ...overrides };
}

const baseData = {
    period: 'day' as const,
    referenceDate: '2026-08-17',
    rangeLabel: '17/08/2026',
    counters: { open: 2, scheduled: 3, completed: 1 },
    agenda: [
        {
            id: 'apt-1',
            starts_at: '2026-08-17T12:00:00Z',
            ends_at: '2026-08-17T12:30:00Z',
            status: 'confirmed',
            status_label: 'Confirmado',
            patient_name: 'Ana Souza',
            service_name: 'Consulta',
            unit_name: 'Matriz',
        },
    ],
    agendaTruncated: false,
    pendingAppointmentRequestsCount: 2,
    pendingAppointmentRequests: [
        {
            id: 'req-1',
            name: 'Bruno Lima',
            phone: '(47) 99999-0000',
            service_name: 'Limpeza',
            created_at: '2026-08-17T10:00:00Z',
        },
    ],
    reminders: [
        {
            id: 'rem-1',
            body: 'Ligar para o laboratório',
            color: 'yellow' as const,
            created_at: '2026-08-17T09:00:00Z',
        },
    ],
};

describe('ProfessionalDashboard', () => {
    it('shows the pending appointment requests alert when there are pending requests', () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: { data: makeData() },
        });

        expect(wrapper.find('[role="status"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Bruno Lima');
    });

    it('hides the alert when there are no pending appointment requests', () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: {
                data: makeData({
                    pendingAppointmentRequestsCount: 0,
                    pendingAppointmentRequests: [],
                }),
            },
        });

        expect(wrapper.find('[role="status"]').exists()).toBe(false);
    });

    it('shows the open/scheduled/completed counters', () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: { data: makeData() },
        });

        expect(wrapper.text()).toContain('Em aberto');
        expect(wrapper.text()).toContain('Agendados');
        expect(wrapper.text()).toContain('Executados');
    });

    it('lists the reminders and lets a professional remove one', async () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: { data: makeData() },
        });

        expect(wrapper.text()).toContain('Ligar para o laboratório');

        await wrapper
            .find('button[aria-label="Remover lembrete"]')
            .trigger('click');

        expect(deleteMock).toHaveBeenCalledWith(
            expect.stringContaining('rem-1'),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('reloads only the professionalDashboard prop when switching periods', async () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: { data: makeData() },
        });

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Semana')
            ?.trigger('click');

        expect(getMock).toHaveBeenCalledWith(
            '/dashboard',
            { period: 'week', date: '2026-08-17' },
            expect.objectContaining({ only: ['professionalDashboard'] }),
        );
    });

    it('submits a new reminder via the form', async () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: { data: makeData() },
        });

        await wrapper.find('textarea').setValue('Confirmar horário com Ana');
        await wrapper.find('form').trigger('submit');

        expect(postMock).toHaveBeenCalledWith(
            expect.stringContaining('/dashboard/lembretes'),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows a Confirmar button for a requested appointment and confirms it', async () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: {
                data: makeData({
                    agenda: [
                        {
                            ...baseData.agenda[0],
                            status: 'requested',
                            status_label: 'Solicitado',
                        },
                    ],
                }),
            },
        });

        const button = wrapper
            .findAll('button')
            .find((btn) => btn.text() === 'Confirmar');
        expect(button).toBeDefined();
        await button?.trigger('click');

        expect(patchMock).toHaveBeenCalledWith(
            expect.stringContaining('/confirm'),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows Check-in for a confirmed past appointment, and Não compareceu once it has started', async () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: {
                data: makeData({
                    agenda: [
                        {
                            ...baseData.agenda[0],
                            status: 'confirmed',
                            status_label: 'Confirmado',
                            starts_at: '2020-01-01T00:00:00Z',
                        },
                    ],
                }),
            },
        });

        const buttons = wrapper.findAll('button').map((btn) => btn.text());
        expect(buttons).toContain('Check-in');
        expect(buttons).toContain('Não compareceu');

        await wrapper
            .findAll('button')
            .find((btn) => btn.text() === 'Check-in')
            ?.trigger('click');

        expect(patchMock).toHaveBeenCalledWith(
            expect.stringContaining('/check-in'),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('never shows Não compareceu for a confirmed appointment that has not started yet', () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: {
                data: makeData({
                    agenda: [
                        {
                            ...baseData.agenda[0],
                            status: 'confirmed',
                            starts_at: '2099-01-01T00:00:00Z',
                        },
                    ],
                }),
            },
        });

        expect(
            wrapper.findAll('button').map((btn) => btn.text()),
        ).not.toContain('Não compareceu');
    });

    it('shows Iniciar atendimento for a checked-in appointment', async () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: {
                data: makeData({
                    agenda: [{ ...baseData.agenda[0], status: 'checked_in' }],
                }),
            },
        });

        await wrapper
            .findAll('button')
            .find((btn) => btn.text() === 'Iniciar atendimento')
            ?.trigger('click');

        expect(patchMock).toHaveBeenCalledWith(
            expect.stringContaining('/start'),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows Concluir for an in-progress appointment', async () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: {
                data: makeData({
                    agenda: [{ ...baseData.agenda[0], status: 'in_progress' }],
                }),
            },
        });

        await wrapper
            .findAll('button')
            .find((btn) => btn.text() === 'Concluir')
            ?.trigger('click');

        expect(patchMock).toHaveBeenCalledWith(
            expect.stringContaining('/complete'),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows no status action for a completed appointment', () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: {
                data: makeData({
                    agenda: [{ ...baseData.agenda[0], status: 'completed' }],
                }),
            },
        });

        const actionLabels = [
            'Confirmar',
            'Check-in',
            'Não compareceu',
            'Iniciar atendimento',
            'Concluir',
        ];
        const buttons = wrapper.findAll('button').map((btn) => btn.text());
        expect(actionLabels.some((label) => buttons.includes(label))).toBe(
            false,
        );
    });
});

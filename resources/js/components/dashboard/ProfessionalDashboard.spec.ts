import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
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
    useForm: (initial: Record<string, unknown>) => {
        const form = {
            ...initial,
            errors: {},
            processing: false,
            post: postMock,
            reset: resetMock,
            transform() {
                return form;
            },
        };

        return form;
    },
}));

function makeData(overrides: Partial<typeof baseData> = {}) {
    return { ...baseData, ...overrides };
}

const baseData = {
    period: 'day' as 'day' | 'week' | 'month',
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
            alarm_at: null as string | null,
            created_at: '2026-08-17T09:00:00Z',
        },
    ],
};

describe('ProfessionalDashboard', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

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

    it('always shows the pending-requests count and a link to the full list, even at zero', () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: {
                data: makeData({
                    pendingAppointmentRequestsCount: 0,
                    pendingAppointmentRequests: [],
                }),
            },
        });

        expect(wrapper.text()).toContain('Pré-agendamentos');
        expect(wrapper.text()).toContain('Ver pré-agendamentos');
        const link = wrapper
            .findAll('a')
            .find((a) => a.text() === 'Ver pré-agendamentos');
        expect(link?.exists()).toBe(true);
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

        await wrapper.find('[aria-label="Remover lembrete"]').trigger('click');

        expect(deleteMock).toHaveBeenCalledWith(
            expect.stringContaining('rem-1'),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows two post-its per row', () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: {
                data: makeData({
                    reminders: [
                        { ...baseData.reminders[0], id: 'rem-1' },
                        { ...baseData.reminders[0], id: 'rem-2' },
                    ],
                }),
            },
        });

        const reminderButtons = wrapper
            .findAll('button')
            .filter((button) =>
                button.text().includes('Ligar para o laboratório'),
            );
        expect(reminderButtons.length).toBe(2);
        expect(reminderButtons[0].element.parentElement?.className).toContain(
            'grid-cols-2',
        );
    });

    it('opens a popup with the full text when a post-it is clicked, without removing it', async () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: { data: makeData() },
        });

        await wrapper
            .findAll('button')
            .find((button) =>
                button.text().includes('Ligar para o laboratório'),
            )
            ?.trigger('click');

        expect(document.body.textContent).toContain('Lembrete');
        expect(deleteMock).not.toHaveBeenCalled();

        const removeButton = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) => button.textContent?.trim() === 'Remover');
        removeButton?.dispatchEvent(new Event('click', { bubbles: true }));

        expect(deleteMock).toHaveBeenCalledWith(
            expect.stringContaining('rem-1'),
            expect.objectContaining({ preserveScroll: true }),
        );

        wrapper.unmount();
    });

    it('fires an alarm popup for a reminder whose alarm time has already passed, and dismisses it via the endpoint', async () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: {
                data: makeData({
                    reminders: [
                        {
                            ...baseData.reminders[0],
                            id: 'rem-alarm',
                            body: 'Tomar remédio',
                            alarm_at: '2020-01-01T00:00:00.000Z',
                        },
                    ],
                }),
            },
        });
        await nextTick();

        expect(document.body.textContent).toContain('Alarme');
        expect(document.body.textContent).toContain('Tomar remédio');

        const dismissButton = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) => button.textContent?.trim() === 'Fechar alarme');
        dismissButton?.dispatchEvent(new Event('click', { bubbles: true }));

        expect(patchMock).toHaveBeenCalledWith(
            expect.stringContaining('/silenciar-alarme'),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );

        wrapper.unmount();
    });

    it('never fires an alarm popup for a reminder without an alarm time', () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: { data: makeData() },
        });

        expect(document.body.textContent).not.toContain('Alarme');

        wrapper.unmount();
    });

    it('jumps directly to a chosen date via the date input, regardless of period', async () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: { data: makeData({ period: 'month' }) },
        });

        const dateInput = wrapper.find('input[type="date"]');
        expect(dateInput.exists()).toBe(true);
        expect((dateInput.element as HTMLInputElement).value).toBe(
            '2026-08-17',
        );

        await dateInput.setValue('2026-09-05');

        expect(getMock).toHaveBeenCalledWith(
            '/dashboard',
            { period: 'month', date: '2026-09-05' },
            expect.objectContaining({ only: ['professionalDashboard'] }),
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

    it('does not show a month calendar for the day or week periods', () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: { data: makeData({ period: 'day' }) },
        });

        expect(wrapper.find('button[aria-label^="Dia"]').exists()).toBe(false);
    });

    it('shows a month calendar with a marker on days that have appointments', () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: {
                data: makeData({
                    period: 'month',
                    referenceDate: '2026-08-17',
                    agenda: [
                        {
                            ...baseData.agenda[0],
                            starts_at: '2026-08-05T12:00:00Z',
                        },
                    ],
                }),
            },
        });

        const dayButtons = wrapper.findAll('button[aria-label^="Dia"]');
        expect(dayButtons.length).toBe(31);
        const day5 = dayButtons.find((b) =>
            b.attributes('aria-label')?.startsWith('Dia 5,'),
        );
        expect(day5?.attributes('aria-label')).toBe('Dia 5, 1 agendamento(s)');
        const day10 = dayButtons.find((b) =>
            b.attributes('aria-label')?.startsWith('Dia 10,'),
        );
        expect(day10?.attributes('aria-label')).toBe(
            'Dia 10, sem agendamentos',
        );
    });

    it('filters the agenda list to a clicked calendar day, and clears the filter via "Ver mês inteiro"', async () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: {
                data: makeData({
                    period: 'month',
                    referenceDate: '2026-08-17',
                    agenda: [
                        {
                            ...baseData.agenda[0],
                            id: 'apt-5',
                            starts_at: '2026-08-05T12:00:00Z',
                            patient_name: 'Paciente do dia 5',
                        },
                        {
                            ...baseData.agenda[0],
                            id: 'apt-12',
                            starts_at: '2026-08-12T12:00:00Z',
                            patient_name: 'Paciente do dia 12',
                        },
                    ],
                }),
            },
        });

        expect(wrapper.text()).toContain('Paciente do dia 5');
        expect(wrapper.text()).toContain('Paciente do dia 12');

        await wrapper
            .findAll('button[aria-label^="Dia"]')
            .find((b) => b.attributes('aria-label')?.startsWith('Dia 5,'))
            ?.trigger('click');

        expect(wrapper.text()).toContain('Paciente do dia 5');
        expect(wrapper.text()).not.toContain('Paciente do dia 12');
        expect(wrapper.text()).toContain('Ver mês inteiro');

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Ver mês inteiro')
            ?.trigger('click');

        expect(wrapper.text()).toContain('Paciente do dia 5');
        expect(wrapper.text()).toContain('Paciente do dia 12');
    });

    it('resets the calendar day filter when the period or reference date changes', async () => {
        const wrapper = mount(ProfessionalDashboard, {
            props: {
                data: makeData({
                    period: 'month',
                    referenceDate: '2026-08-17',
                    agenda: [
                        {
                            ...baseData.agenda[0],
                            starts_at: '2026-08-05T12:00:00Z',
                            patient_name: 'Paciente do dia 5',
                        },
                    ],
                }),
            },
        });

        await wrapper
            .findAll('button[aria-label^="Dia"]')
            .find((b) => b.attributes('aria-label')?.startsWith('Dia 5,'))
            ?.trigger('click');
        expect(wrapper.text()).toContain('Ver mês inteiro');

        await wrapper.setProps({
            data: makeData({
                period: 'month',
                referenceDate: '2026-09-17',
                agenda: [],
            }),
        });

        expect(wrapper.text()).not.toContain('Ver mês inteiro');
    });
});

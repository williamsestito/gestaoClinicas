import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import Reschedule from './Reschedule.vue';

const { routerMock } = vi.hoisted(() => ({
    routerMock: { get: vi.fn() },
}));

const formState = reactive({
    starts_at: '',
    errors: {} as Record<string, string>,
    processing: false,
    put: vi.fn(),
});

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    router: routerMock,
    useForm: () => formState,
}));

const patient = { id: 'patient-1' };
const appointment = {
    id: 'appt-1',
    starts_at: '2026-08-26T09:00:00Z',
    unit_id: 'unit-1',
    professional_id: 'prof-1',
    service_id: 'service-1',
    professional_name: 'Dra. Juliana Cruz',
    service_name: 'Consulta',
    duration_minutes: 30,
};

function mockFetchSequence(
    responses: Array<
        { date?: string; is_available?: boolean; time?: string }[]
    >,
) {
    let call = 0;
    vi.stubGlobal(
        'fetch',
        vi.fn().mockImplementation(() => {
            const data = responses[call] ?? [];
            call += 1;

            return Promise.resolve({
                ok: true,
                json: async () => ({ data }),
            });
        }),
    );
}

describe('patient-portal/appointments/Reschedule', () => {
    beforeEach(() => {
        formState.starts_at = '';
        vi.clearAllMocks();
        vi.setSystemTime(new Date('2026-08-20T12:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('renders the service and professional name', () => {
        mockFetchSequence([[], []]);
        const wrapper = mount(Reschedule, { props: { patient, appointment } });

        expect(wrapper.text()).toContain('Consulta');
        expect(wrapper.text()).toContain('Dra. Juliana Cruz');
    });

    it('loads dates and times for the appointment on mount, scoped to the same unit/service/professional', async () => {
        mockFetchSequence([[], []]);
        mount(Reschedule, { props: { patient, appointment } });
        await flushPromises();

        expect(fetch).toHaveBeenCalledWith(
            expect.stringContaining(
                '/portal/agendamentos/disponibilidade/datas',
            ),
            expect.objectContaining({
                headers: { Accept: 'application/json' },
            }),
        );
        const datesCall = (fetch as unknown as { mock: { calls: string[][] } })
            .mock.calls[0][0];
        expect(datesCall).toContain('unit_id=unit-1');
        expect(datesCall).toContain('service_id=service-1');
        expect(datesCall).toContain('professional_id=prof-1');

        expect(fetch).toHaveBeenCalledWith(
            expect.stringContaining(
                '/portal/agendamentos/disponibilidade/horarios',
            ),
            expect.anything(),
        );
    });

    it('disables an already-past day even when the backend marks it as available', async () => {
        mockFetchSequence([
            [
                { date: '2026-08-19', is_available: true },
                { date: '2026-08-26', is_available: true },
            ],
            [],
        ]);
        const wrapper = mount(Reschedule, { props: { patient, appointment } });
        await flushPromises();

        const buttons = wrapper.findAll('button[aria-label^="Dia"]');
        const day19 = buttons.find(
            (b) => b.attributes('aria-label') === 'Dia 19, indisponível',
        );
        const day26 = buttons.find((b) =>
            b.attributes('aria-label')?.startsWith('Dia 26'),
        );
        expect(day19?.attributes('disabled')).toBeDefined();
        expect(day26?.attributes('aria-label')).toBe('Dia 26, disponível');
        expect(day26?.attributes('disabled')).toBeUndefined();
    });

    it('reloads times and clears a previously chosen slot when a new date is picked', async () => {
        mockFetchSequence([
            [{ date: '2026-08-27', is_available: true }],
            [{ time: '09:00' }],
            [{ time: '14:00' }],
        ]);
        const wrapper = mount(Reschedule, { props: { patient, appointment } });
        await flushPromises();

        await wrapper
            .findAll('button')
            .find((b) => b.text() === '09:00')
            ?.trigger('click');
        expect(formState.starts_at).toBe('2026-08-26T09:00:00');

        await wrapper
            .findAll('button[aria-label^="Dia"]')
            .find((b) => b.attributes('aria-label')?.startsWith('Dia 27'))
            ?.trigger('click');
        await flushPromises();

        expect(formState.starts_at).toBe('');
        expect(wrapper.text()).toContain('14:00');
    });

    it('submits the new starts_at to the reschedule update route', async () => {
        mockFetchSequence([[], [{ time: '09:00' }]]);
        const wrapper = mount(Reschedule, { props: { patient, appointment } });
        await flushPromises();

        await wrapper
            .findAll('button')
            .find((b) => b.text() === '09:00')
            ?.trigger('click');
        await wrapper.find('form').trigger('submit');

        expect(formState.put).toHaveBeenCalledWith(
            expect.stringContaining(
                `/portal/pacientes/${patient.id}/agendamentos/${appointment.id}/reagendar`,
            ),
        );
    });

    it('navigates back to the appointments list on cancel', async () => {
        mockFetchSequence([[], []]);
        const wrapper = mount(Reschedule, { props: { patient, appointment } });
        await flushPromises();

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Voltar')
            ?.trigger('click');

        expect(routerMock.get).toHaveBeenCalledWith(
            expect.stringContaining(
                `/portal/pacientes/${patient.id}/agendamentos`,
            ),
        );
    });
});

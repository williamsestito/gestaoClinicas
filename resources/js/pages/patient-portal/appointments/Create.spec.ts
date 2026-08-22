import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import Create from './Create.vue';

const { routerMock } = vi.hoisted(() => ({
    routerMock: { get: vi.fn() },
}));

const formState = reactive({
    unit_id: '',
    professional_id: '',
    service_id: '',
    starts_at: '',
    notes: '',
    errors: {} as Record<string, string>,
    processing: false,
    post: vi.fn(),
});

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    router: routerMock,
    useForm: () => formState,
}));

const patient = { id: 'patient-1', name: 'Ana Souza' };
const units = [{ id: 'unit-1', name: 'Unidade Centro' }];
const professionals = [{ id: 'prof-1', display_name: 'Dra. Ana' }];
const services = [{ id: 'service-1', name: 'Consulta' }];

describe('patient-portal/appointments/Create', () => {
    beforeEach(() => {
        formState.unit_id = '';
        formState.professional_id = '';
        formState.service_id = '';
        formState.starts_at = '';
        formState.notes = '';
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => ({
                    slots: [{ time: '09:00', duration_minutes: 30 }],
                }),
            }),
        );
    });

    it('renders the patient name in the page description', () => {
        const wrapper = mount(Create, {
            props: { patient, units, professionals, services },
        });

        expect(wrapper.text()).toContain('Ana Souza');
    });

    it('loads and displays available slots once unit, professional, service and date are set', async () => {
        const wrapper = mount(Create, {
            props: { patient, units, professionals, services },
        });

        formState.unit_id = 'unit-1';
        formState.professional_id = 'prof-1';
        formState.service_id = 'service-1';
        await wrapper.find('#slot-picker-date').setValue('2026-09-01');
        await flushPromises();

        expect(fetch).toHaveBeenCalledWith(
            expect.stringContaining('/portal/agendamentos/horarios'),
            expect.objectContaining({
                headers: { Accept: 'application/json' },
            }),
        );
        expect(wrapper.text()).toContain('09:00');
    });

    it('sets starts_at when a slot is selected and submits to the patient-scoped store route', async () => {
        const wrapper = mount(Create, {
            props: { patient, units, professionals, services },
        });

        formState.unit_id = 'unit-1';
        formState.professional_id = 'prof-1';
        formState.service_id = 'service-1';
        await wrapper.find('#slot-picker-date').setValue('2026-09-01');
        await flushPromises();

        await wrapper
            .findAll('button')
            .find((button) => button.text() === '09:00')
            ?.trigger('click');

        expect(formState.starts_at).toBe('2026-09-01T09:00:00');

        await wrapper.find('form').trigger('submit');

        expect(formState.post).toHaveBeenCalledWith(
            expect.stringContaining(
                `/portal/pacientes/${patient.id}/agendamentos`,
            ),
        );
    });
});

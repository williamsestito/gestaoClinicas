import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import Create from './Create.vue';

const { routerMock } = vi.hoisted(() => ({
    routerMock: { get: vi.fn() },
}));

const formState = reactive({
    unit_id: '',
    professional_id: '',
    patient_id: '',
    service_id: '',
    starts_at: '',
    notes: '',
    appointment_request_id: '',
    waitlist_entry_id: '',
    resource_ids: [] as string[],
    session_package_id: '',
    recurrence_weeks: undefined as number | undefined,
    errors: {} as Record<string, string>,
    processing: false,
    post: vi.fn(),
});

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    router: routerMock,
    useForm: (initial: Record<string, unknown>) => {
        Object.assign(formState, initial);

        return formState;
    },
}));

vi.mock('@/components/appointments/PatientSearchSelect.vue', () => ({
    default: { template: '<div />' },
}));

const units = [
    { id: 'unit-1', name: 'Unidade Centro' },
    { id: 'unit-2', name: 'Unidade Norte' },
];
const professionals = [
    { id: 'prof-1', display_name: 'Dra. Ana' },
    { id: 'prof-2', display_name: 'Dr. João' },
];
const services = [{ id: 'service-1', name: 'Consulta' }];

function resetFormState() {
    formState.unit_id = '';
    formState.professional_id = '';
    formState.patient_id = '';
    formState.service_id = '';
    formState.starts_at = '';
    formState.notes = '';
    formState.appointment_request_id = '';
    formState.waitlist_entry_id = '';
    formState.resource_ids = [];
    formState.session_package_id = '';
    formState.recurrence_weeks = undefined;
}

describe('settings/appointments/Create', () => {
    beforeEach(() => {
        resetFormState();
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => ({ slots: [] }),
            }),
        );
    });

    it('shows editable unit/professional selects when there is no prefill', () => {
        const wrapper = mount(Create, {
            props: {
                units,
                professionals,
                services,
                resources: [],
                prefill: null,
            },
        });

        expect(wrapper.find('#appointment-unit').element.tagName).toBe(
            'SELECT',
        );
        expect(wrapper.find('#appointment-professional').element.tagName).toBe(
            'SELECT',
        );
    });

    it('locks unit and professional as read-only text when converting a pré-agendamento that already carries them', () => {
        const wrapper = mount(Create, {
            props: {
                units,
                professionals,
                services,
                resources: [],
                prefill: {
                    name: 'Ana Souza',
                    phone: '(47) 99999-0000',
                    notes: null,
                    unit_id: 'unit-2',
                    professional_id: 'prof-2',
                },
            },
        });

        const unitField = wrapper.find('#appointment-unit');
        const professionalField = wrapper.find('#appointment-professional');
        expect(unitField.element.tagName).toBe('P');
        expect(professionalField.element.tagName).toBe('P');
        expect(unitField.text()).toBe('Unidade Norte');
        expect(professionalField.text()).toBe('Dr. João');
        expect(formState.unit_id).toBe('unit-2');
        expect(formState.professional_id).toBe('prof-2');
    });

    it('keeps unit editable but locks only the professional when the pré-agendamento has no unit on file', () => {
        const wrapper = mount(Create, {
            props: {
                units,
                professionals,
                services,
                resources: [],
                prefill: {
                    name: 'Ana Souza',
                    phone: '(47) 99999-0000',
                    notes: null,
                    unit_id: null,
                    professional_id: 'prof-1',
                },
            },
        });

        expect(wrapper.find('#appointment-unit').element.tagName).toBe(
            'SELECT',
        );
        expect(wrapper.find('#appointment-professional').element.tagName).toBe(
            'P',
        );
    });
});

import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { computed, nextTick, reactive, ref } from 'vue';
import type {
    AvailabilityDate,
    AvailabilityProfessional,
    AvailabilityService,
    AvailabilitySpecialty,
    AvailabilityTime,
    AvailabilityUnit,
} from '@/composables/usePatientAvailabilitySearch';
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

const loadUnits = vi.fn();
const selectUnit = vi.fn((id: string) => {
    selectedUnitId.value = id;
});
const selectSpecialty = vi.fn((id: string | null) => {
    selectedSpecialtyId.value = id;
});
const selectService = vi.fn((id: string) => {
    selectedServiceId.value = id;
});
const selectProfessional = vi.fn((id: string) => {
    selectedProfessionalId.value = id;
});
const selectDate = vi.fn((date: string) => {
    selectedDate.value = date;
});
const changeMonth = vi.fn();

const units = ref<AvailabilityUnit[]>([]);
const specialties = ref<AvailabilitySpecialty[]>([]);
const services = ref<AvailabilityService[]>([]);
const professionals = ref<AvailabilityProfessional[]>([]);
const dates = ref<AvailabilityDate[]>([]);
const times = ref<AvailabilityTime[]>([]);
const selectedUnitId = ref<string | null>(null);
const selectedSpecialtyId = ref<string | null>(null);
const selectedServiceId = ref<string | null>(null);
const selectedProfessionalId = ref<string | null>(null);
const selectedDate = ref<string | null>(null);
const currentMonth = ref('2026-08');
const loadingKeys = ref<Set<string>>(new Set());
const error = ref<string | null>(null);
const isLoading = (key: string) => loadingKeys.value.has(key);

vi.mock('@/composables/usePatientAvailabilitySearch', () => ({
    usePatientAvailabilitySearch: () => ({
        ANY_PROFESSIONAL: 'any',
        units,
        specialties,
        services,
        professionals,
        dates,
        times,
        selectedUnitId,
        selectedSpecialtyId,
        selectedServiceId,
        selectedProfessionalId,
        selectedDate,
        currentMonth,
        isLoading,
        error,
        isAnyProfessional: computed(
            () => selectedProfessionalId.value === 'any',
        ),
        loadUnits,
        selectUnit,
        selectSpecialty,
        selectService,
        selectProfessional,
        selectDate,
        changeMonth,
    }),
}));

const patient = { id: 'patient-1', name: 'Ana Souza' };

const defaultUnit: AvailabilityUnit = {
    id: 'unit-1',
    name: 'Centro',
    neighborhood: null,
    city: null,
    state: null,
};

function resetState() {
    units.value = [];
    specialties.value = [];
    services.value = [];
    professionals.value = [];
    dates.value = [];
    times.value = [];
    selectedUnitId.value = null;
    selectedSpecialtyId.value = null;
    selectedServiceId.value = null;
    selectedProfessionalId.value = null;
    selectedDate.value = null;
    currentMonth.value = '2026-08';
    loadingKeys.value = new Set();
    error.value = null;
    formState.unit_id = '';
    formState.professional_id = '';
    formState.service_id = '';
    formState.starts_at = '';
    formState.notes = '';
    vi.clearAllMocks();
}

function setupSingleSlot() {
    units.value = [defaultUnit];
    selectedUnitId.value = 'unit-1';
    selectedServiceId.value = 'svc-1';
    selectedDate.value = '2026-08-03';
    times.value = [
        {
            time: '09:00',
            professional_id: 'prof-1',
            professional_name: 'Dra. Ana Souza',
            unit_name: 'Centro',
            service_name: 'Consulta',
            duration_minutes: 30,
        },
    ];
}

describe('patient-portal/appointments/Create', () => {
    beforeEach(() => {
        resetState();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders the patient name in the page description', () => {
        const wrapper = mount(Create, { props: { patient } });

        expect(wrapper.text()).toContain('Ana Souza');
    });

    it('calls loadUnits on mount', () => {
        mount(Create, { props: { patient } });

        expect(loadUnits).toHaveBeenCalledTimes(1);
    });

    it('shows an empty-state message when there are no units', () => {
        const wrapper = mount(Create, { props: { patient } });

        expect(wrapper.text()).toContain(
            'Nenhuma unidade disponível para agendamento no momento.',
        );
    });

    it('reveals the calendar only once a service is selected', async () => {
        units.value = [defaultUnit];
        selectedUnitId.value = 'unit-1';
        const wrapper = mount(Create, { props: { patient } });

        expect(wrapper.text()).not.toContain('Selecione uma data');

        selectedServiceId.value = 'svc-1';
        await nextTick();

        expect(wrapper.text()).toContain('Selecione uma data');
    });

    it('disables an already-past day even when the backend marks it as available', async () => {
        vi.setSystemTime(new Date('2026-08-02T12:00:00Z'));
        units.value = [defaultUnit];
        selectedUnitId.value = 'unit-1';
        selectedServiceId.value = 'svc-1';
        currentMonth.value = '2026-08';
        dates.value = [
            { date: '2026-08-01', is_available: true },
            { date: '2026-08-03', is_available: true },
        ];

        const wrapper = mount(Create, { props: { patient } });
        await nextTick();

        const buttons = wrapper.findAll('button[aria-label^="Dia"]');
        expect(buttons[0].attributes('aria-label')).toBe('Dia 1, indisponível');
        expect(buttons[0].attributes('disabled')).toBeDefined();
        expect(buttons[1].attributes('aria-label')).toBe('Dia 3, disponível');
        expect(buttons[1].attributes('disabled')).toBeUndefined();
    });

    it('sets unit/service/professional/starts_at when a time slot is chosen and submits to the patient-scoped store route', async () => {
        setupSingleSlot();

        const wrapper = mount(Create, { props: { patient } });
        await nextTick();

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('09:00'))
            ?.trigger('click');

        expect(formState.unit_id).toBe('unit-1');
        expect(formState.service_id).toBe('svc-1');
        expect(formState.professional_id).toBe('prof-1');
        expect(formState.starts_at).toBe('2026-08-03T09:00:00');
        expect(wrapper.text()).toContain('Dra. Ana Souza');

        await wrapper.find('form').trigger('submit');

        expect(formState.post).toHaveBeenCalledWith(
            expect.stringContaining(
                `/portal/pacientes/${patient.id}/agendamentos`,
            ),
        );
    });

    it('clears a previously chosen time when the professional filter changes', async () => {
        setupSingleSlot();

        const wrapper = mount(Create, { props: { patient } });
        await nextTick();

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('09:00'))
            ?.trigger('click');
        expect(formState.starts_at).toBe('2026-08-03T09:00:00');

        selectedProfessionalId.value = 'prof-2';
        await nextTick();

        expect(formState.starts_at).toBe('');
    });

    it('disables the submit button until a time slot has been chosen', async () => {
        setupSingleSlot();

        const wrapper = mount(Create, { props: { patient } });
        await nextTick();

        const submitButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Solicitar agendamento')!;
        expect(submitButton.attributes('disabled')).toBeDefined();

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('09:00'))
            ?.trigger('click');

        expect(submitButton.attributes('disabled')).toBeUndefined();
    });

    it('navigates back to the appointments list on cancel', async () => {
        const wrapper = mount(Create, { props: { patient } });

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Cancelar')
            ?.trigger('click');

        expect(routerMock.get).toHaveBeenCalledWith(
            expect.stringContaining(
                `/portal/pacientes/${patient.id}/agendamentos`,
            ),
        );
    });
});

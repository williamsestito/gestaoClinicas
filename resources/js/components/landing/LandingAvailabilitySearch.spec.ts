import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { computed, nextTick, ref } from 'vue';
import type {
    AvailabilityDate,
    AvailabilityProfessional,
    AvailabilityService,
    AvailabilitySpecialty,
    AvailabilityTime,
    AvailabilityUnit,
} from '@/composables/usePublicAvailabilitySearch';
import LandingAvailabilitySearch from './LandingAvailabilitySearch.vue';

const loadUnits = vi.fn();
const selectUnit = vi.fn();
const selectSpecialty = vi.fn();
const selectService = vi.fn();
const selectProfessional = vi.fn();
const selectDate = vi.fn();
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
const loading = ref<string | null>(null);
const error = ref<string | null>(null);

vi.mock('@/composables/usePublicAvailabilitySearch', () => ({
    usePublicAvailabilitySearch: () => ({
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
        loading,
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

const schedulingServiceId = ref<number | null>(null);
const selectedProfessionalName = ref<string | null>(null);
const preferredDate = ref<string | null>(null);
const preferredPeriod = ref<string | null>(null);

vi.mock('@/composables/useLandingScheduling', () => ({
    useLandingScheduling: () => ({
        selectedServiceId: schedulingServiceId,
        selectedProfessionalName,
        preferredDate,
        preferredPeriod,
    }),
}));

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
    loading.value = null;
    error.value = null;
    vi.clearAllMocks();
    schedulingServiceId.value = null;
    selectedProfessionalName.value = null;
    preferredDate.value = null;
    preferredPeriod.value = null;
    Element.prototype.scrollIntoView = vi.fn();
}

const defaultUnit: AvailabilityUnit = {
    id: 'unit-1',
    name: 'Centro',
    neighborhood: null,
    city: null,
    state: null,
};

describe('LandingAvailabilitySearch', () => {
    beforeEach(() => {
        resetState();
    });

    it('calls loadUnits on mount', () => {
        mount(LandingAvailabilitySearch);

        expect(loadUnits).toHaveBeenCalledTimes(1);
    });

    it('shows an empty-state message when there are no units and nothing is loading', () => {
        const wrapper = mount(LandingAvailabilitySearch);

        expect(wrapper.text()).toContain(
            'Nenhuma unidade disponível para consulta no momento.',
        );
    });

    it('does not show filters beyond unit until a unit is selected', () => {
        units.value = [defaultUnit];
        const wrapper = mount(LandingAvailabilitySearch);

        expect(wrapper.find('#availability-specialty').exists()).toBe(false);
        expect(wrapper.find('#availability-service').exists()).toBe(false);
    });

    it('reveals the calendar only once a service is selected', async () => {
        units.value = [defaultUnit];
        selectedUnitId.value = 'unit-1';
        const wrapper = mount(LandingAvailabilitySearch);

        expect(wrapper.text()).not.toContain('Selecione uma data');

        selectedServiceId.value = 'svc-1';
        await nextTick();

        expect(wrapper.text()).toContain('Selecione uma data');
    });

    it('renders the calendar grid with correct leading blanks and marks available/unavailable days', async () => {
        units.value = [defaultUnit];
        selectedUnitId.value = 'unit-1';
        selectedServiceId.value = 'svc-1';
        currentMonth.value = '2026-08';
        // 2026-08-01 é sábado (getDay() === 6) → 6 células em branco antes do dia 1.
        dates.value = [
            { date: '2026-08-01', is_available: false },
            { date: '2026-08-02', is_available: false },
            { date: '2026-08-03', is_available: true },
        ];

        const wrapper = mount(LandingAvailabilitySearch);
        await nextTick();

        const buttons = wrapper.findAll('button[aria-label^="Dia"]');
        expect(buttons).toHaveLength(3);
        expect(buttons[0].attributes('aria-label')).toBe('Dia 1, indisponível');
        expect(buttons[0].attributes('disabled')).toBeDefined();
        expect(buttons[2].attributes('aria-label')).toBe('Dia 3, disponível');
        expect(buttons[2].attributes('disabled')).toBeUndefined();
    });

    it('calls selectDate only when clicking an available day, never a disabled one', async () => {
        units.value = [defaultUnit];
        selectedUnitId.value = 'unit-1';
        selectedServiceId.value = 'svc-1';
        dates.value = [
            { date: '2026-08-01', is_available: false },
            { date: '2026-08-03', is_available: true },
        ];

        const wrapper = mount(LandingAvailabilitySearch);
        await nextTick();

        const buttons = wrapper.findAll('button[aria-label^="Dia"]');
        await buttons[0].trigger('click');
        expect(selectDate).not.toHaveBeenCalled();

        await buttons[1].trigger('click');
        expect(selectDate).toHaveBeenCalledWith('2026-08-03');
    });

    it('shows a loading indicator instead of the grid while the calendar is loading', async () => {
        units.value = [defaultUnit];
        selectedUnitId.value = 'unit-1';
        selectedServiceId.value = 'svc-1';
        loading.value = 'dates';

        const wrapper = mount(LandingAvailabilitySearch);
        await nextTick();

        expect(wrapper.text()).toContain('Carregando calendário…');
        expect(wrapper.findAll('button[aria-label^="Dia"]')).toHaveLength(0);
    });

    it('shows an empty-state message when there are no times for the selected date', async () => {
        units.value = [defaultUnit];
        selectedUnitId.value = 'unit-1';
        selectedServiceId.value = 'svc-1';
        selectedDate.value = '2026-08-03';
        times.value = [];

        const wrapper = mount(LandingAvailabilitySearch);
        await nextTick();

        expect(wrapper.text()).toContain(
            'Nenhum horário disponível para os filtros selecionados.',
        );
    });

    it('shows a loading indicator while times are loading', async () => {
        units.value = [defaultUnit];
        selectedUnitId.value = 'unit-1';
        selectedServiceId.value = 'svc-1';
        selectedDate.value = '2026-08-03';
        loading.value = 'times';

        const wrapper = mount(LandingAvailabilitySearch);
        await nextTick();

        expect(wrapper.text()).toContain('Carregando horários…');
    });

    it('renders one button per time slot and shows the professional name only in "any professional" mode', async () => {
        units.value = [defaultUnit];
        selectedUnitId.value = 'unit-1';
        selectedServiceId.value = 'svc-1';
        selectedDate.value = '2026-08-03';
        selectedProfessionalId.value = 'any';
        services.value = [
            {
                id: 'svc-1',
                name: 'Consulta',
                description: null,
                default_duration_minutes: 30,
            },
        ];
        times.value = [
            {
                time: '09:00',
                professional_id: 'prof-1',
                professional_name: 'Dra. Ana Souza',
                unit_name: 'Centro',
                service_name: 'Consulta',
                duration_minutes: 30,
            },
            {
                time: '10:00',
                professional_id: 'prof-2',
                professional_name: 'Dr. João Lima',
                unit_name: 'Centro',
                service_name: 'Consulta',
                duration_minutes: 30,
            },
        ];

        const wrapper = mount(LandingAvailabilitySearch);
        await nextTick();

        const text = wrapper.text();
        expect(text).toContain('09:00');
        expect(text).toContain('Dra. Ana Souza');
        expect(text).toContain('10:00');
        expect(text).toContain('Dr. João Lima');
        // Nunca cria reserva — só preenche o formulário manual abaixo.
        expect(text).toContain('Nada é reservado agora');
    });

    it('hides the professional name in a time slot when a specific professional is already selected', async () => {
        units.value = [defaultUnit];
        selectedUnitId.value = 'unit-1';
        selectedServiceId.value = 'svc-1';
        selectedDate.value = '2026-08-03';
        selectedProfessionalId.value = 'prof-1';
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

        const wrapper = mount(LandingAvailabilitySearch);
        await nextTick();

        expect(wrapper.text()).not.toContain('Dra. Ana Souza');
    });

    function setupSingleSlot() {
        units.value = [defaultUnit];
        selectedUnitId.value = 'unit-1';
        selectedServiceId.value = 'svc-1';
        selectedDate.value = '2026-08-03';
        services.value = [
            {
                id: 'svc-1',
                name: 'Consulta de avaliação',
                description: null,
                default_duration_minutes: 30,
            },
        ];
        times.value = [
            {
                time: '09:00',
                professional_id: 'prof-1',
                professional_name: 'Dra. Ana Souza',
                unit_name: 'Centro',
                service_name: 'Consulta de avaliação',
                duration_minutes: 30,
            },
        ];
    }

    it('writes a free-text summary and structured date/period into the scheduling composable when a time slot is chosen, never a service id', async () => {
        setupSingleSlot();

        const wrapper = mount(LandingAvailabilitySearch, {
            attachTo: document.body,
        });
        await nextTick();

        const button = wrapper
            .findAll('button')
            .find((btn) => btn.text().includes('09:00'));
        await button?.trigger('click');

        // Service.id (ULID operacional) nunca é gravado no campo numérico
        // selectedServiceId (que espera um SiteService promocional).
        expect(schedulingServiceId.value).toBeNull();
        expect(selectedProfessionalName.value).toContain('Dra. Ana Souza');
        expect(selectedProfessionalName.value).toContain(
            'Consulta de avaliação',
        );
        expect(selectedProfessionalName.value).toContain('09:00');
        expect(preferredDate.value).toBe('2026-08-03');
        expect(preferredPeriod.value).toBe('Manhã');

        wrapper.unmount();
    });

    it('never validates or submits anything on click — it only prefills, scrolling to the personal data fields', async () => {
        setupSingleSlot();
        const scrollSpy = vi.fn();
        Element.prototype.scrollIntoView = scrollSpy;
        const nameField = document.createElement('div');
        nameField.id = 'name';
        document.body.appendChild(nameField);

        const wrapper = mount(LandingAvailabilitySearch, {
            attachTo: document.body,
        });
        await nextTick();

        await wrapper
            .findAll('button')
            .find((btn) => btn.text().includes('09:00'))
            ?.trigger('click');

        expect(scrollSpy).toHaveBeenCalledWith(
            expect.objectContaining({ behavior: 'smooth', block: 'center' }),
        );
        expect(wrapper.find('[role="status"]').exists()).toBe(false);

        wrapper.unmount();
        nameField.remove();
    });

    it('updates the scheduling composable again on a second, different time slot click', async () => {
        setupSingleSlot();
        times.value = [
            ...times.value,
            {
                time: '16:30',
                professional_id: 'prof-2',
                professional_name: 'Dr. João Lima',
                unit_name: 'Centro',
                service_name: 'Consulta de avaliação',
                duration_minutes: 30,
            },
        ];

        const wrapper = mount(LandingAvailabilitySearch, {
            attachTo: document.body,
        });
        await nextTick();
        const findButton = (time: string) =>
            wrapper.findAll('button').find((btn) => btn.text().includes(time));

        await findButton('09:00')?.trigger('click');
        expect(selectedProfessionalName.value).toContain('Dra. Ana Souza');
        expect(preferredPeriod.value).toBe('Manhã');

        await findButton('16:30')?.trigger('click');
        expect(selectedProfessionalName.value).toContain('Dr. João Lima');
        expect(preferredPeriod.value).toBe('Tarde');

        wrapper.unmount();
    });

    it('renders the error message with role="alert" when the search fails', () => {
        units.value = [defaultUnit];
        error.value = 'Não foi possível carregar as unidades. Tente novamente.';

        const wrapper = mount(LandingAvailabilitySearch);

        const alert = wrapper.find('[role="alert"]');
        expect(alert.exists()).toBe(true);
        expect(alert.text()).toBe(
            'Não foi possível carregar as unidades. Tente novamente.',
        );
    });
});

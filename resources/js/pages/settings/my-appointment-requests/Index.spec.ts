import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import Index from './Index.vue';

const { routerMock } = vi.hoisted(() => ({
    routerMock: {
        get: vi.fn(),
        patch: vi.fn(),
    },
}));

const instantFormState = reactive({
    patient_id: '',
    unit_id: '',
    professional_id: '',
    service_id: '',
    starts_at: '',
    appointment_request_id: '',
    notes: '',
    errors: {} as Record<string, string>,
    processing: false,
    post: vi.fn(),
    clearErrors: vi.fn(),
    reset: vi.fn(),
});

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    router: routerMock,
    useForm: (initial: Record<string, unknown>) => {
        Object.assign(instantFormState, initial);

        return instantFormState;
    },
}));

vi.mock('@/components/appointments/PatientSearchSelect.vue', () => ({
    default: {
        template:
            '<input data-testid="patient-search-select" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
        props: ['modelValue', 'error'],
        emits: ['update:modelValue'],
    },
}));

type Row = {
    id: string;
    name: string;
    phone: string;
    email: string | null;
    document: string | null;
    service_name: string | null;
    preferred_period: string | null;
    preferred_date: string | null;
    notes: string | null;
    internal_notes: string | null;
    status: 'pending' | 'contacted' | 'scheduled' | 'cancelled';
    status_label: string;
    appointment_status: string | null;
    appointment_status_label: string | null;
    created_at: string | null;
    professional_id: string | null;
    professional_name: string | null;
    unit_id: string | null;
    unit_name: string | null;
    preferred_service_id: string | null;
    preferred_service_name: string | null;
    preferred_starts_at: string | null;
    patient_id: string | null;
    patient_name: string | null;
};

function makeRequest(overrides: Partial<Row> = {}): Row {
    return {
        id: '01ABC',
        name: 'Ana Souza',
        phone: '(47) 99999-0000',
        email: 'ana@example.com',
        document: null,
        service_name: 'Limpeza de pele',
        preferred_period: 'Manhã',
        preferred_date: null,
        notes: null,
        internal_notes: null,
        status: 'pending',
        status_label: 'Aguardando contato',
        appointment_status: null,
        appointment_status_label: null,
        created_at: '2026-07-20T10:00:00Z',
        professional_id: 'prof-1',
        professional_name: 'Dra Juliana Cruz',
        unit_id: null,
        unit_name: null,
        preferred_service_id: null,
        preferred_service_name: null,
        preferred_starts_at: null,
        patient_id: null,
        patient_name: null,
        ...overrides,
    };
}

function makeInstantSchedulableRequest(overrides: Partial<Row> = {}): Row {
    return makeRequest({
        unit_id: 'unit-1',
        unit_name: 'Unidade Norte',
        preferred_service_id: 'service-1',
        preferred_service_name: 'Consulta',
        preferred_starts_at: '2026-09-15T12:00:00+00:00',
        patient_id: 'patient-1',
        patient_name: 'Ana Souza',
        ...overrides,
    });
}

function resetInstantFormState() {
    instantFormState.patient_id = '';
    instantFormState.unit_id = '';
    instantFormState.professional_id = '';
    instantFormState.service_id = '';
    instantFormState.starts_at = '';
    instantFormState.appointment_request_id = '';
    instantFormState.notes = '';
    instantFormState.errors = {};
    instantFormState.processing = false;
}

function mountIndex(
    requestsData: Row[] | null = [makeRequest()],
    canCreateAppointments = true,
) {
    return mount(Index, {
        props: {
            requests:
                requestsData === null
                    ? null
                    : {
                          data: requestsData,
                          links: [],
                          total: requestsData.length,
                      },
            canCreateAppointments,
        },
    });
}

describe('settings/my-appointment-requests/Index', () => {
    beforeEach(() => {
        resetInstantFormState();
    });

    it('shows an empty state for a user without a linked professional', () => {
        const wrapper = mountIndex(null);

        expect(wrapper.text()).toContain(
            'Você não possui um cadastro profissional vinculado.',
        );
    });

    it('shows an empty state when there are no requests', () => {
        const wrapper = mountIndex([]);

        expect(wrapper.text()).toContain('Nenhum pré-agendamento encontrado.');
    });

    it('lists the essential fields of each request', () => {
        const wrapper = mountIndex();

        expect(wrapper.text()).toContain('Ana Souza');
        expect(wrapper.text()).toContain('(47) 99999-0000');
        expect(wrapper.text()).toContain('Limpeza de pele');
    });

    it('shows an "Agendar" link to convert a pending request into a real appointment', () => {
        const wrapper = mountIndex([makeRequest({ status: 'pending' })]);

        const link = wrapper.findAll('a').find((a) => a.text() === 'Agendar');
        expect(link?.exists()).toBe(true);
        expect(link?.attributes('href')).toContain(
            'appointment_request_id=01ABC',
        );
    });

    it('hides "Agendar" when the professional cannot create appointments', () => {
        const wrapper = mountIndex([makeRequest({ status: 'pending' })], false);

        expect(
            wrapper.findAll('a').find((a) => a.text() === 'Agendar'),
        ).toBeUndefined();
    });

    it('hides "Agendar" once the request has already been converted', () => {
        const wrapper = mountIndex([
            makeRequest({
                status: 'pending',
                appointment_status: 'confirmed',
                appointment_status_label: 'Confirmado',
            }),
        ]);

        expect(
            wrapper.findAll('a').find((a) => a.text() === 'Agendar'),
        ).toBeUndefined();
    });

    it('hides "Agendar" for an already-cancelled request', () => {
        const wrapper = mountIndex([makeRequest({ status: 'cancelled' })]);

        expect(
            wrapper.findAll('a').find((a) => a.text() === 'Agendar'),
        ).toBeUndefined();
    });

    it('never offers "Agendado" as a selectable status — it only exists via real conversion', () => {
        const wrapper = mountIndex();

        expect(wrapper.text()).not.toContain('Agendado');
    });

    it('shows the linked real appointment status when the lead has been converted', () => {
        const wrapper = mountIndex([
            makeRequest({
                appointment_status: 'confirmed',
                appointment_status_label: 'Confirmado',
            }),
        ]);

        expect(wrapper.text()).toContain('Agendamento real:');
        expect(wrapper.text()).toContain('Confirmado');
    });

    it('never shows a real-appointment status line for a lead that was not converted', () => {
        const wrapper = mountIndex();

        expect(wrapper.text()).not.toContain('Agendamento real:');
    });

    it('disables the status select once the lead has been converted, so it can never desync from the real appointment again', () => {
        const wrapper = mountIndex([
            makeRequest({
                status: 'contacted',
                appointment_status: 'confirmed',
                appointment_status_label: 'Confirmado',
            }),
        ]);

        expect(
            wrapper.findComponent({ name: 'SelectRoot' }).props('disabled'),
        ).toBe(true);
    });

    it('sends a status update via router.patch', async () => {
        const wrapper = mountIndex();

        await wrapper
            .findComponent({ name: 'SelectRoot' })
            .vm.$emit('update:modelValue', 'contacted');

        expect(routerMock.patch).toHaveBeenCalledWith(
            expect.stringContaining('/status'),
            { status: 'contacted' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('saves an internal note via router.patch', async () => {
        const wrapper = mountIndex();

        const textarea = wrapper.find('textarea');
        await textarea.setValue('Liguei e confirmei.');

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Salvar observação')
            ?.trigger('click');

        expect(routerMock.patch).toHaveBeenCalledWith(
            expect.stringContaining('/notes'),
            { internal_notes: 'Liguei e confirmei.' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows "Agendar" as a popup trigger (button, not a link) when the lead already carries unit/service/exact time', () => {
        const wrapper = mountIndex([makeInstantSchedulableRequest()]);

        expect(
            wrapper.findAll('a').find((a) => a.text() === 'Agendar'),
        ).toBeUndefined();
        expect(
            wrapper.findAll('button').find((b) => b.text() === 'Agendar'),
        ).toBeDefined();
    });

    it('still falls back to the Etapa 3.7 conversion link when the lead lacks the structured fields', () => {
        const wrapper = mountIndex([makeRequest({ status: 'pending' })]);

        const link = wrapper.findAll('a').find((a) => a.text() === 'Agendar');
        expect(link?.exists()).toBe(true);
        expect(link?.attributes('href')).toContain(
            'appointment_request_id=01ABC',
        );
    });

    it('opens the confirmation popup pre-filled with the pré-agendamento data and posts to the existing appointments endpoint', async () => {
        const wrapper = mountIndex([makeInstantSchedulableRequest()]);

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Agendar')
            ?.trigger('click');

        expect(document.body.textContent).toContain('Consulta');
        expect(document.body.textContent).toContain('Unidade Norte');
        expect(document.body.textContent).toContain('Ana Souza');
        expect(instantFormState.patient_id).toBe('patient-1');
        expect(instantFormState.unit_id).toBe('unit-1');
        expect(instantFormState.professional_id).toBe('prof-1');
        expect(instantFormState.service_id).toBe('service-1');
        expect(instantFormState.starts_at).toBe('2026-09-15T12:00:00+00:00');
        expect(instantFormState.appointment_request_id).toBe('01ABC');

        const confirmButton = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) =>
            button.textContent?.includes('Confirmar agendamento'),
        );
        await confirmButton?.dispatchEvent(
            new Event('click', { bubbles: true }),
        );

        expect(instantFormState.post).toHaveBeenCalledWith(
            '/settings/appointments',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('lets the professional search a patient in the popup when the lead was never matched to one', async () => {
        const wrapper = mountIndex([
            makeInstantSchedulableRequest({
                patient_id: null,
                patient_name: null,
            }),
        ]);

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Agendar')
            ?.trigger('click');

        const patientSearchInput = document.body.querySelector(
            '[data-testid="patient-search-select"]',
        );
        expect(patientSearchInput).not.toBeNull();

        await patientSearchInput?.dispatchEvent(
            new Event('input', { bubbles: true }),
        );
    });

    it('shows the "Mostrar cancelados" checkbox unchecked by default, and reloads with show_cancelled when toggled', async () => {
        const wrapper = mountIndex();

        const checkbox = wrapper.find('input[type="checkbox"]');
        expect((checkbox.element as HTMLInputElement).checked).toBe(false);

        await checkbox.setValue(true);

        expect(routerMock.get).toHaveBeenCalledWith(
            expect.stringContaining('/settings/meus-pre-agendamentos'),
            { show_cancelled: '1' },
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('reflects showCancelled=true from the server in the checkbox state', () => {
        const wrapper = mount(Index, {
            props: {
                requests: {
                    data: [makeRequest()],
                    links: [],
                    total: 1,
                },
                canCreateAppointments: true,
                showCancelled: true,
            },
        });

        const checkbox = wrapper.find('input[type="checkbox"]');
        expect((checkbox.element as HTMLInputElement).checked).toBe(true);
    });
});

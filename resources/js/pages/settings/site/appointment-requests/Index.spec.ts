import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type { AppointmentRequestSummary } from '@/types/site';
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

function makeRequest(
    overrides: Partial<AppointmentRequestSummary> = {},
): AppointmentRequestSummary {
    return {
        id: '01ABC',
        name: 'Ana Souza',
        phone: '(47) 99999-0000',
        email: 'ana@example.com',
        service_name: 'Limpeza de pele',
        preferred_period: 'Manhã',
        preferred_date: null,
        notes: null,
        internal_notes: null,
        utm_data: null,
        status: 'pending',
        status_label: 'Aguardando contato',
        created_at: '2026-07-20T10:00:00Z',
        updated_at: '2026-07-20T10:00:00Z',
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

const professionals = [
    { id: 'prof-1', display_name: 'Dra Juliana Cruz' },
    { id: 'prof-2', display_name: 'Dr João Paiva' },
];

function mountIndex(
    requestsData: AppointmentRequestSummary[] = [makeRequest()],
    canCreateAppointments = true,
) {
    return mount(Index, {
        props: {
            requests: {
                data: requestsData,
                links: [],
                total: requestsData.length,
            },
            professionals,
            filters: {},
            can_create_appointments: canCreateAppointments,
        },
    });
}

describe('settings/site/appointment-requests/Index', () => {
    beforeEach(() => {
        instantFormState.patient_id = '';
        instantFormState.unit_id = '';
        instantFormState.professional_id = '';
        instantFormState.service_id = '';
        instantFormState.starts_at = '';
        instantFormState.appointment_request_id = '';
        instantFormState.notes = '';
        instantFormState.errors = {};
        instantFormState.processing = false;
    });

    it('shows an empty state when there are no requests', () => {
        const wrapper = mountIndex([]);

        expect(wrapper.text()).toContain('Nenhuma solicitação encontrada.');
    });

    it('lists the essential fields of each request', () => {
        const wrapper = mountIndex();

        expect(wrapper.text()).toContain('Ana Souza');
        expect(wrapper.text()).toContain('(47) 99999-0000');
        expect(wrapper.text()).toContain('Limpeza de pele');
        expect(wrapper.text()).toContain('Manhã');
    });

    it('generates a WhatsApp link with the normalized number and an encoded message mentioning the service', () => {
        const wrapper = mountIndex();

        const link = wrapper.find('a[href*="wa.me"]');
        expect(link.exists()).toBe(true);
        expect(link.attributes('href')).toContain('wa.me/5547999990000');
        expect(link.attributes('href')).toContain(
            encodeURIComponent('Limpeza de pele'),
        );
        expect(link.attributes('target')).toBe('_blank');
        expect(link.attributes('rel')).toBe('noopener noreferrer');
    });

    it('does not generate a broken WhatsApp message when there is no service', () => {
        const wrapper = mountIndex([makeRequest({ service_name: null })]);

        const link = wrapper.find('a[href*="wa.me"]');
        const href = link.attributes('href') ?? '';
        expect(href).not.toContain('undefined');
        expect(href).not.toContain('null');
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

    it('saves an internal note via router.patch without exposing it as public notes', async () => {
        const wrapper = mountIndex();

        const textarea = wrapper.find('textarea');
        await textarea.setValue('Ligamos, sem retorno ainda.');

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Salvar observação')
            ?.trigger('click');

        expect(routerMock.patch).toHaveBeenCalledWith(
            expect.stringContaining('/notes'),
            { internal_notes: 'Ligamos, sem retorno ainda.' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('submits filters via router.get', async () => {
        const wrapper = mountIndex();

        await wrapper.find('#request-search').setValue('Ana');
        await wrapper.find('form').trigger('submit');

        expect(routerMock.get).toHaveBeenCalledWith(
            expect.any(String),
            expect.objectContaining({ search: 'Ana' }),
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('displays utm origin data when present', () => {
        const wrapper = mountIndex([
            makeRequest({ utm_data: { utm_source: 'google' } }),
        ]);

        expect(wrapper.text()).toContain('utm_source=google');
    });

    it('shows the convert-to-appointment link for a pending lead when the user can create appointments', () => {
        const wrapper = mountIndex([makeRequest({ status: 'pending' })], true);

        const link = wrapper.find('a[href*="appointment_request_id"]');
        expect(link.exists()).toBe(true);
        expect(link.attributes('href')).toContain('01ABC');
    });

    it('hides the convert-to-appointment link when the lead is already scheduled', () => {
        const wrapper = mountIndex(
            [makeRequest({ status: 'scheduled' })],
            true,
        );

        expect(wrapper.find('a[href*="appointment_request_id"]').exists()).toBe(
            false,
        );
    });

    it('hides the convert-to-appointment link when the user lacks permission', () => {
        const wrapper = mountIndex([makeRequest({ status: 'pending' })], false);

        expect(wrapper.find('a[href*="appointment_request_id"]').exists()).toBe(
            false,
        );
    });

    it('shows which professional each lead was requested for', () => {
        const wrapper = mountIndex([
            makeRequest({ professional_name: 'Dra Juliana Cruz' }),
        ]);

        expect(wrapper.text()).toContain('Profissional: Dra Juliana Cruz');
    });

    it('lists the professionals in the filter select and submits professional_id', async () => {
        const wrapper = mountIndex();

        const options = wrapper
            .find('#request-professional')
            .findAll('option')
            .map((option) => option.text());
        expect(options).toEqual(['Todos', 'Dra Juliana Cruz', 'Dr João Paiva']);

        await wrapper.find('#request-professional').setValue('prof-2');
        await wrapper.find('form').trigger('submit');

        expect(routerMock.get).toHaveBeenCalledWith(
            expect.any(String),
            expect.objectContaining({ professional_id: 'prof-2' }),
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('shows "Confirmar agendamento" as a button (not the manual conversion link) when the lead already carries unit/service/exact time, for any professional', async () => {
        const request = makeRequest({
            professional_id: 'prof-2',
            professional_name: 'Dr João Paiva',
            unit_id: 'unit-1',
            unit_name: 'Unidade Norte',
            preferred_service_id: 'service-1',
            preferred_service_name: 'Consulta',
            preferred_starts_at: '2026-09-15T12:00:00+00:00',
            patient_id: 'patient-1',
            patient_name: 'Ana Souza',
        });
        const wrapper = mountIndex([request]);

        expect(wrapper.find('a[href*="appointment_request_id"]').exists()).toBe(
            false,
        );

        const scheduleButton = wrapper
            .findAll('button')
            .find((b) => b.text() === 'Confirmar agendamento');
        expect(scheduleButton).toBeDefined();

        await scheduleButton?.trigger('click');

        expect(document.body.textContent).toContain('Dr João Paiva');
        expect(document.body.textContent).toContain('Unidade Norte');
        expect(instantFormState.professional_id).toBe('prof-2');
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

    it('never offers to confirm a lead already converted into a real appointment, even if its status was manually changed away from "scheduled"', () => {
        // Achado real: alguém trocava o status de volta para "Contato
        // realizado" depois da conversão pelo select solto — o registro
        // continuava com `appointment_id` preenchido (refletido aqui por
        // appointment_status_label), então a ação de confirmar precisa
        // ficar escondida independente do texto de `status`.
        const wrapper = mountIndex([
            makeRequest({
                status: 'contacted',
                appointment_status: 'confirmed',
                appointment_status_label: 'Confirmado',
            }),
        ]);

        expect(
            wrapper
                .findAll('button')
                .find((b) => b.text() === 'Confirmar agendamento'),
        ).toBeUndefined();
        expect(wrapper.find('a[href*="appointment_request_id"]').exists()).toBe(
            false,
        );
    });

    it('shows the linked real appointment status even on the admin listing', () => {
        const wrapper = mountIndex([
            makeRequest({
                appointment_status: 'confirmed',
                appointment_status_label: 'Confirmado',
            }),
        ]);

        expect(wrapper.text()).toContain('Agendamento real:');
        expect(wrapper.text()).toContain('Confirmado');
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
});

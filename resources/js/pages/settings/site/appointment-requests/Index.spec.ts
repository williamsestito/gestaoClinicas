import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import type { AppointmentRequestSummary } from '@/types/site';
import Index from './Index.vue';

const { routerMock } = vi.hoisted(() => ({
    routerMock: {
        get: vi.fn(),
        patch: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    router: routerMock,
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
        ...overrides,
    };
}

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
            filters: {},
            can_create_appointments: canCreateAppointments,
        },
    });
}

describe('settings/site/appointment-requests/Index', () => {
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
        const wrapper = mountIndex([makeRequest({ status: 'scheduled' })], true);

        expect(wrapper.find('a[href*="appointment_request_id"]').exists()).toBe(false);
    });

    it('hides the convert-to-appointment link when the user lacks permission', () => {
        const wrapper = mountIndex([makeRequest({ status: 'pending' })], false);

        expect(wrapper.find('a[href*="appointment_request_id"]').exists()).toBe(false);
    });
});

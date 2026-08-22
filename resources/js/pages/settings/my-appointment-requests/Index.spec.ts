import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
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

type Row = {
    id: string;
    name: string;
    phone: string;
    email: string | null;
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
};

function makeRequest(overrides: Partial<Row> = {}): Row {
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
        status: 'pending',
        status_label: 'Aguardando contato',
        appointment_status: null,
        appointment_status_label: null,
        created_at: '2026-07-20T10:00:00Z',
        ...overrides,
    };
}

function mountIndex(requestsData: Row[] | null = [makeRequest()]) {
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
        },
    });
}

describe('settings/my-appointment-requests/Index', () => {
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

    it('never shows a convert-to-appointment action — self-service scheduling is out of scope', () => {
        const wrapper = mountIndex();

        expect(wrapper.text()).not.toContain('Converter em agendamento');
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
});

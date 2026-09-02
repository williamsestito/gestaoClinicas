import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Index from './Index.vue';

const { routerMock } = vi.hoisted(() => ({
    routerMock: { get: vi.fn() },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    router: routerMock,
}));

vi.mock('@/components/patients/PatientSummaryModal.vue', () => ({
    default: {
        template: '<div data-testid="summary-modal">{{ modelValue }}</div>',
        props: ['modelValue'],
        emits: ['update:modelValue'],
    },
}));

function makePatients(overrides: Partial<Record<string, unknown>> = {}) {
    return {
        data: [
            {
                id: 'patient-1',
                name: 'Ana Souza',
                preferred_name: null,
                document: '123.456.789-00',
                birth_date: '1990-01-01',
                phone: '(47) 99999-0000',
                status: 'active' as const,
                deleted_at: null,
                full_access: true,
                relationship_label: 'Paciente principal',
            },
        ],
        links: [],
        total: 1,
        ...overrides,
    };
}

describe('settings/my-patients/Index', () => {
    it('shows an empty state for a user without a linked professional', () => {
        const wrapper = mount(Index, {
            props: { patients: null, filters: {} },
        });

        expect(wrapper.text()).toContain(
            'Você não possui um cadastro profissional vinculado.',
        );
    });

    it('shows an empty state when there are no patients', () => {
        const wrapper = mount(Index, {
            props: {
                patients: makePatients({ data: [], total: 0 }),
                filters: {},
            },
        });

        expect(wrapper.text()).toContain('Nenhum paciente encontrado');
    });

    it('opens the summary modal with the patient id when "Ver" is clicked', async () => {
        const wrapper = mount(Index, {
            props: { patients: makePatients(), filters: {} },
        });

        expect(wrapper.text()).toContain('Ana Souza');
        expect(wrapper.find('[data-testid="summary-modal"]').text()).toBe('');

        const verButton = wrapper
            .findAll('button')
            .find((b) => b.text() === 'Ver');
        await verButton?.trigger('click');

        expect(wrapper.find('[data-testid="summary-modal"]').text()).toBe(
            'patient-1',
        );
    });

    it('shows "Vender" only for a patient with full access', () => {
        const fullAccessWrapper = mount(Index, {
            props: { patients: makePatients(), filters: {} },
        });

        expect(
            fullAccessWrapper.findAll('a').find((a) => a.text() === 'Vender'),
        ).not.toBeUndefined();

        const summaryOnlyWrapper = mount(Index, {
            props: {
                patients: makePatients({
                    data: [
                        {
                            id: 'patient-2',
                            name: 'Bruno Lima',
                            preferred_name: null,
                            document: null,
                            birth_date: '1990-01-01',
                            phone: '(47) 98888-0000',
                            status: 'active' as const,
                            deleted_at: null,
                            full_access: false,
                            relationship_label: 'Pré-agendamento pendente',
                        },
                    ],
                }),
                filters: {},
            },
        });

        expect(summaryOnlyWrapper.text()).toContain('Bruno Lima');
        expect(summaryOnlyWrapper.text()).toContain('Pré-agendamento pendente');
        expect(
            summaryOnlyWrapper.findAll('a').find((a) => a.text() === 'Vender'),
        ).toBeUndefined();
        expect(
            summaryOnlyWrapper
                .findAll('button')
                .find((b) => b.text() === 'Ver'),
        ).not.toBeUndefined();
    });

    it('submits the search/status filters via router.get', async () => {
        const wrapper = mount(Index, {
            props: { patients: makePatients(), filters: {} },
        });

        await wrapper.find('#my-patient-search').setValue('Ana');
        await wrapper.find('form').trigger('submit');

        expect(routerMock.get).toHaveBeenCalledWith(
            expect.stringContaining('/settings/meus-pacientes'),
            expect.objectContaining({ search: 'Ana' }),
            expect.objectContaining({ preserveState: true }),
        );
    });
});

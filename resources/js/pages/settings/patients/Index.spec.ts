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
            },
        ],
        links: [],
        total: 1,
        ...overrides,
    };
}

const professionals = [
    { id: 'prof-1', display_name: 'Dra Juliana Cruz' },
    { id: 'prof-2', display_name: 'Dr João Paiva' },
];

describe('settings/patients/Index', () => {
    it('shows an empty state when there are no patients', () => {
        const wrapper = mount(Index, {
            props: {
                patients: makePatients({ data: [], total: 0 }),
                professionals,
                filters: {},
            },
        });

        expect(wrapper.text()).toContain('Nenhum paciente encontrado');
    });

    it('lists the professionals in the filter select', () => {
        const wrapper = mount(Index, {
            props: { patients: makePatients(), professionals, filters: {} },
        });

        const options = wrapper
            .find('#patient-professional')
            .findAll('option')
            .map((option) => option.text());

        expect(options).toEqual(['Todos', 'Dra Juliana Cruz', 'Dr João Paiva']);
    });

    it('submits the search/status/professional filters via router.get', async () => {
        const wrapper = mount(Index, {
            props: { patients: makePatients(), professionals, filters: {} },
        });

        await wrapper.find('#patient-search').setValue('Ana');
        await wrapper.find('#patient-professional').setValue('prof-1');
        await wrapper.find('form').trigger('submit');

        expect(routerMock.get).toHaveBeenCalledWith(
            expect.stringContaining('/settings/patients'),
            expect.objectContaining({
                search: 'Ana',
                professional_id: 'prof-1',
            }),
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('opens the summary modal with the patient id when "Ver" is clicked', async () => {
        const wrapper = mount(Index, {
            props: { patients: makePatients(), professionals, filters: {} },
        });

        const verButton = wrapper
            .findAll('button')
            .find((b) => b.text() === 'Ver');
        await verButton?.trigger('click');

        expect(wrapper.find('[data-testid="summary-modal"]').text()).toBe(
            'patient-1',
        );
    });
});

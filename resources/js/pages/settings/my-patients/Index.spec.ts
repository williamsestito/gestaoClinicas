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

    it('lists a patient with "Ver" and "Prontuário" links', () => {
        const wrapper = mount(Index, {
            props: { patients: makePatients(), filters: {} },
        });

        expect(wrapper.text()).toContain('Ana Souza');

        const verLink = wrapper.findAll('a').find((a) => a.text() === 'Ver');
        const recordLink = wrapper
            .findAll('a')
            .find((a) => a.text() === 'Prontuário');

        expect(verLink?.attributes('href')).toContain('patient-1');
        expect(recordLink?.attributes('href')).toContain('patient-1');
        expect(recordLink?.attributes('href')).toContain('prontuarios');
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

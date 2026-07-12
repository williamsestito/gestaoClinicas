import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Index from './Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>' },
}));

describe('settings/units/Index', () => {
    it('shows an empty state when there are no units', () => {
        const wrapper = mount(Index, {
            props: { units: [] },
        });

        expect(wrapper.text()).toContain('Nenhuma unidade cadastrada ainda.');
    });

    it('lists the units when present', () => {
        const wrapper = mount(Index, {
            props: {
                units: [
                    {
                        id: '1',
                        organization_id: 'org-1',
                        legal_entity_id: 'le-1',
                        name: 'Unidade Centro',
                        code: 'UN-0001',
                        slug: 'unidade-centro',
                        status: 'active',
                        is_headquarters: true,
                        timezone: 'America/Sao_Paulo',
                        email: null,
                        phone: null,
                        whatsapp: null,
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain('Unidade Centro');
        expect(wrapper.text()).toContain('Matriz');
    });
});

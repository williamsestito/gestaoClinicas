import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Index from './Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
}));

function makeSales(overrides: Partial<Record<string, unknown>> = {}) {
    return {
        data: [
            {
                id: 'sale-1',
                patient_name: 'Ana Souza',
                unit_name: 'Unidade Centro',
                status: 'confirmed',
                status_label: 'Confirmada',
                total_cents: 10000,
                created_at: '2026-09-01T12:00:00Z',
            },
        ],
        links: [],
        total: 1,
        ...overrides,
    };
}

describe('settings/sales/Index', () => {
    it('shows an empty state when there are no sales', () => {
        const wrapper = mount(Index, {
            props: { sales: makeSales({ data: [], total: 0 }), filters: {} },
        });

        expect(wrapper.text()).toContain('Nenhuma venda registrada');
    });

    it('lists a sale with a link to its detail page', () => {
        const wrapper = mount(Index, {
            props: { sales: makeSales(), filters: {} },
        });

        expect(wrapper.text()).toContain('Ana Souza');
        expect(wrapper.text()).toContain('Confirmada');

        const link = wrapper.findAll('a').find((a) => a.text() === 'Ver');
        expect(link?.attributes('href')).toContain('sale-1');
    });
});

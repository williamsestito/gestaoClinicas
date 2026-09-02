import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Index from './Index.vue';

const { routerPatch, routerDelete, routerPost } = vi.hoisted(() => ({
    routerPatch: vi.fn(),
    routerDelete: vi.fn(),
    routerPost: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    router: { patch: routerPatch, delete: routerDelete, post: routerPost },
}));

function makeProduct(overrides: Partial<Record<string, unknown>> = {}) {
    return {
        id: 'prod-1',
        name: 'Creme hidratante',
        code: 'PRD-01',
        unit_of_measure: 'un',
        price_cents: 4000,
        status: 'active' as const,
        deleted_at: null,
        ...overrides,
    };
}

describe('settings/products/Index', () => {
    it('shows an empty state when there are no products', () => {
        const wrapper = mount(Index, { props: { products: [] } });

        expect(wrapper.text()).toContain('Nenhum produto cadastrado');
    });

    it('lists a product with its formatted price', () => {
        const wrapper = mount(Index, { props: { products: [makeProduct()] } });

        expect(wrapper.text()).toContain('Creme hidratante');
        expect(wrapper.text()).toContain('R$');
    });

    it('shows a dash when the product has no price', () => {
        const wrapper = mount(Index, {
            props: { products: [makeProduct({ price_cents: null })] },
        });

        expect(wrapper.text()).toContain('—');
    });

    it('calls router.patch on the deactivate route for an active product', async () => {
        const wrapper = mount(Index, { props: { products: [makeProduct()] } });

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Inativar')
            ?.trigger('click');

        expect(routerPatch).toHaveBeenCalledWith(
            expect.stringContaining('deactivate'),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows a restore button for a soft-deleted product', () => {
        const wrapper = mount(Index, {
            props: {
                products: [makeProduct({ deleted_at: '2026-01-01T00:00:00Z' })],
            },
        });

        expect(
            wrapper
                .findAll('button')
                .some((button) => button.text() === 'Restaurar'),
        ).toBe(true);
    });
});

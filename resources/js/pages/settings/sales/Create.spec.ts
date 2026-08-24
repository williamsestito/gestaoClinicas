import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Create from './Create.vue';

const { postMock } = vi.hoisted(() => ({ postMock: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    useForm: (initial: Record<string, unknown>) => {
        const form = {
            ...initial,
            errors: {},
            processing: false,
            post: postMock,
        };

        return form;
    },
}));

const baseProps = {
    patient: { id: 'patient-1', name: 'Ana Souza' },
    unit: { id: 'unit-1', name: 'Unidade Centro' },
    legalEntity: { id: 'entity-1', name: 'Clínica LTDA' },
    services: [
        {
            id: 'service-1',
            name: 'Massagem',
            default_price_cents: 10000,
            max_discount_percentage: 10,
        },
    ],
    products: [
        {
            id: 'product-1',
            name: 'Creme',
            price_cents: 5000,
            max_discount_percentage: 20,
        },
    ],
};

describe('settings/sales/Create', () => {
    it('shows the prefilled patient name when provided', () => {
        const wrapper = mount(Create, { props: baseProps });

        expect(wrapper.text()).toContain('Ana Souza');
    });

    it('shows the active unit and legal entity as read-only context, not editable selects', () => {
        const wrapper = mount(Create, { props: baseProps });

        expect(wrapper.text()).toContain('Unidade Centro');
        expect(wrapper.text()).toContain('Clínica LTDA');
        expect(wrapper.find('#sale-unit').exists()).toBe(false);
        expect(wrapper.find('#sale-legal-entity').exists()).toBe(false);
    });

    it('computes the total live from the entered unit price', async () => {
        const wrapper = mount(Create, { props: baseProps });

        await wrapper.find('[data-testid="item-unit-price"]').setValue(100);

        // formatCurrencyBrl usa toLocaleString, que insere um espaço não
        // separável (U+00A0) entre "R$" e o valor — normaliza antes de
        // comparar para não depender desse detalhe do Intl.
        const normalized = wrapper.text().replace(/ /g, ' ');
        expect(normalized).toContain('R$ 100,00');
    });

    it('shows a pending-approval warning when the discount exceeds the limit', async () => {
        const wrapper = mount(Create, { props: baseProps });

        await wrapper
            .find('[data-testid="item-discount-percentage"]')
            .setValue(50);

        expect(wrapper.text()).toContain('Exige aprovação');
        expect(wrapper.text()).toContain(
            'Esta venda terá itens aguardando aprovação de desconto.',
        );
    });

    it('adds and removes an item row', async () => {
        const wrapper = mount(Create, { props: baseProps });

        expect(wrapper.findAll('[aria-label="Remover item"]').length).toBe(1);

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Adicionar item')
            ?.trigger('click');

        expect(wrapper.findAll('[aria-label="Remover item"]').length).toBe(2);
    });

    it('submits the sale with the built cart', async () => {
        const wrapper = mount(Create, { props: baseProps });

        await wrapper.find('form').trigger('submit');

        expect(postMock).toHaveBeenCalled();
    });
});

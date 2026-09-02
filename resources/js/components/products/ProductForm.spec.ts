import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ProductForm from './ProductForm.vue';

const { postMock, putMock } = vi.hoisted(() => ({
    postMock: vi.fn(),
    putMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useForm: (initial: Record<string, unknown>) => {
        const form = {
            ...initial,
            errors: {},
            processing: false,
            post: postMock,
            put: putMock,
        };

        return form;
    },
}));

describe('components/products/ProductForm', () => {
    it('submits a new product via post', async () => {
        const wrapper = mount(ProductForm, { props: { mode: 'create' } });

        await wrapper.find('#product-name').setValue('Creme hidratante');
        await wrapper.find('#product-code').setValue('PRD-01');
        await wrapper.find('form').trigger('submit');

        expect(postMock).toHaveBeenCalled();
        expect(putMock).not.toHaveBeenCalled();
    });

    it('submits an existing product via put, pre-filling the current values', async () => {
        const wrapper = mount(ProductForm, {
            props: {
                mode: 'edit',
                product: {
                    id: 'prod-1',
                    name: 'Sabonete',
                    code: 'SAB-01',
                    barcode: null,
                    unit_of_measure: 'un',
                    cost: 5,
                    margin_percentage: 100,
                    price: 10,
                    max_discount_percentage: 10,
                    internal_notes: null,
                },
            },
        });

        expect(
            (wrapper.find('#product-name').element as HTMLInputElement).value,
        ).toBe('Sabonete');

        await wrapper.find('form').trigger('submit');

        expect(putMock).toHaveBeenCalledWith(
            expect.stringContaining('prod-1'),
            expect.anything(),
        );
    });

    it('emits cancel when the cancel button is clicked', async () => {
        const wrapper = mount(ProductForm, { props: { mode: 'create' } });

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Cancelar')
            ?.trigger('click');

        expect(wrapper.emitted('cancel')).toBeTruthy();
    });
});

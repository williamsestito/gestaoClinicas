import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import AddressFields from './AddressFields.vue';

describe('AddressFields', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('fills street, neighborhood, city and state after a valid CEP lookup', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => ({
                    postal_code: '01310100',
                    street: 'Avenida Paulista',
                    neighborhood: 'Bela Vista',
                    city: 'São Paulo',
                    state: 'SP',
                }),
            }),
        );

        const wrapper = mount(AddressFields, {
            props: {
                modelValue: {
                    postal_code: '01310100',
                    street: '',
                    number: '',
                    complement: '',
                    neighborhood: '',
                    city: '',
                    state: '',
                },
                states: ['SP', 'RJ'],
            },
        });

        await wrapper.find('#address-postal-code').trigger('blur');
        await new Promise((resolve) => setTimeout(resolve, 0));

        const emitted = wrapper.emitted('update:modelValue');
        expect(emitted).toBeTruthy();

        const lastEmitted = emitted![emitted!.length - 1][0] as Record<
            string,
            string
        >;
        expect(lastEmitted.street).toBe('Avenida Paulista');
        expect(lastEmitted.city).toBe('São Paulo');
        expect(lastEmitted.state).toBe('SP');
    });

    it('keeps the form editable when the CEP is not found', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({ ok: false, status: 404 }),
        );

        const wrapper = mount(AddressFields, {
            props: {
                modelValue: {
                    postal_code: '00000000',
                    street: '',
                    number: '',
                    complement: '',
                    neighborhood: '',
                    city: '',
                    state: '',
                },
                states: ['SP'],
            },
        });

        await wrapper.find('#address-postal-code').trigger('blur');
        await new Promise((resolve) => setTimeout(resolve, 0));
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('CEP não encontrado');
        expect(
            wrapper.find('#address-street').attributes('disabled'),
        ).toBeUndefined();
    });
});

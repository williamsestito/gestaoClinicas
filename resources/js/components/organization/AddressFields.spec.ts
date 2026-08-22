import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { AddressForm } from '@/types/organization';
import AddressFields from './AddressFields.vue';

const blankAddress = {
    postal_code: '',
    street: '',
    number: '',
    complement: '',
    neighborhood: '',
    city: '',
    state: '',
};

const successResponse = {
    postal_code: '01310100',
    street: 'Avenida Paulista',
    neighborhood: 'Bela Vista',
    city: 'São Paulo',
    state: 'SP',
    source: 'viacep',
};

describe('AddressFields', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('fills street, neighborhood, city and state after a valid CEP lookup on blur', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => successResponse,
            }),
        );

        const wrapper = mount(AddressFields, {
            props: {
                modelValue: { ...blankAddress, postal_code: '01310100' },
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
        expect(wrapper.text()).toContain(
            'Endereço localizado. Confira os dados antes de continuar.',
        );
    });

    it('automatically looks up the address once the CEP reaches 8 digits', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => successResponse,
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(AddressFields, {
            props: {
                modelValue: { ...blankAddress, postal_code: '0131010' },
                states: ['SP'],
            },
        });

        await wrapper.setProps({
            modelValue: { ...blankAddress, postal_code: '01310100' },
        });
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('looks up the address when the search button is clicked manually', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => successResponse,
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(AddressFields, {
            props: {
                modelValue: { ...blankAddress, postal_code: '01310100' },
                states: ['SP'],
            },
        });

        await wrapper
            .find('button[aria-label="Buscar endereço pelo CEP"]')
            .trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('does not repeat the request for the same CEP already queried', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => successResponse,
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(AddressFields, {
            props: {
                modelValue: { ...blankAddress, postal_code: '01310100' },
                states: ['SP'],
            },
        });

        await wrapper.find('#address-postal-code').trigger('blur');
        await new Promise((resolve) => setTimeout(resolve, 0));
        await wrapper.find('#address-postal-code').trigger('blur');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('keeps the form editable and shows a plain-language message when the CEP is not found', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({ ok: false, status: 404 }),
        );

        const wrapper = mount(AddressFields, {
            props: {
                modelValue: { ...blankAddress, postal_code: '00000000' },
                states: ['SP'],
            },
        });

        await wrapper.find('#address-postal-code').trigger('blur');
        await new Promise((resolve) => setTimeout(resolve, 0));
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain(
            'Não foi possível localizar esse CEP automaticamente. Preencha o endereço manualmente.',
        );
        expect(
            wrapper.find('#address-street').attributes('disabled'),
        ).toBeUndefined();
    });

    it('shows a distinct message for a network failure instead of a plain not-found', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({ ok: false, status: 500 }),
        );

        const wrapper = mount(AddressFields, {
            props: {
                modelValue: { ...blankAddress, postal_code: '01310100' },
                states: ['SP'],
            },
        });

        await wrapper.find('#address-postal-code').trigger('blur');
        await new Promise((resolve) => setTimeout(resolve, 0));
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain(
            'Não foi possível consultar o CEP neste momento. Você pode preencher o endereço manualmente.',
        );
    });

    it('emits the updated number and complement as the person types them, not just on CEP lookup', async () => {
        const wrapper = mount(AddressFields, {
            props: {
                modelValue: { ...blankAddress },
                states: ['SP'],
            },
        });

        await wrapper.find('#address-number').setValue('123');
        let emitted = wrapper.emitted('update:modelValue');
        expect(emitted).toBeTruthy();
        expect(
            (emitted![emitted!.length - 1][0] as Record<string, string>).number,
        ).toBe('123');

        await wrapper.find('#address-complement').setValue('Sala 4');
        emitted = wrapper.emitted('update:modelValue');
        expect(
            (emitted![emitted!.length - 1][0] as Record<string, string>)
                .complement,
        ).toBe('Sala 4');
    });

    it('never loses a manually typed number/complement when the parent re-renders with the emitted value', async () => {
        const wrapper = mount(AddressFields, {
            props: {
                modelValue: { ...blankAddress },
                states: ['SP'],
            },
        });

        await wrapper.find('#address-number').setValue('123');
        const emitted = wrapper.emitted('update:modelValue')!;
        const latest = emitted[emitted.length - 1][0] as AddressForm;

        await wrapper.setProps({ modelValue: latest });

        expect(
            (wrapper.find('#address-number').element as HTMLInputElement).value,
        ).toBe('123');
    });

    it('disables the manual search button while a lookup is in progress', async () => {
        let resolveFetch: (value: unknown) => void = () => {};
        vi.stubGlobal(
            'fetch',
            vi.fn(
                () =>
                    new Promise((resolve) => {
                        resolveFetch = resolve;
                    }),
            ),
        );

        const wrapper = mount(AddressFields, {
            props: {
                modelValue: { ...blankAddress, postal_code: '01310100' },
                states: ['SP'],
            },
        });

        await wrapper.find('#address-postal-code').trigger('blur');
        await wrapper.vm.$nextTick();

        expect(
            wrapper
                .find('button[aria-label="Buscar endereço pelo CEP"]')
                .attributes('disabled'),
        ).toBeDefined();

        resolveFetch({ ok: true, json: async () => successResponse });
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
});

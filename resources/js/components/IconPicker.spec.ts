import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import IconPicker from './IconPicker.vue';

describe('IconPicker', () => {
    it('shows a placeholder label when no icon is selected', () => {
        const wrapper = mount(IconPicker, {
            props: { modelValue: null },
            attachTo: document.body,
        });

        expect(wrapper.text()).toContain('Escolher ícone');
    });

    it('shows the readable label of the currently selected icon', () => {
        const wrapper = mount(IconPicker, {
            props: { modelValue: 'stethoscope' },
            attachTo: document.body,
        });

        expect(wrapper.text()).toContain('Atendimento clínico');
    });

    it('opens a visual grid of icons with names when the trigger is clicked, not a text field', async () => {
        const wrapper = mount(IconPicker, {
            props: { modelValue: null },
            attachTo: document.body,
        });

        expect(wrapper.find('input[type="text"]').exists()).toBe(false);

        await wrapper.find('button').trigger('click');
        await wrapper.vm.$nextTick();

        const iconButtons = document.body.querySelectorAll(
            '[aria-label="Buscar ícone por nome"] ~ * button, .grid button',
        );
        expect(iconButtons.length).toBeGreaterThan(0);
        expect(document.body.textContent).toContain('Cuidado / acolhimento');

        wrapper.unmount();
    });

    it('filters icons by search term (name or key)', async () => {
        const wrapper = mount(IconPicker, {
            props: { modelValue: null },
            attachTo: document.body,
        });

        await wrapper.find('button').trigger('click');
        await wrapper.vm.$nextTick();

        const search = document.querySelector(
            'input[aria-label="Buscar ícone por nome"]',
        ) as HTMLInputElement;
        search.value = 'segurança';
        search.dispatchEvent(new Event('input'));
        await wrapper.vm.$nextTick();

        expect(document.body.textContent).toContain('Segurança verificada');
        expect(document.body.textContent).not.toContain('Cuidado / acolhimento');

        wrapper.unmount();
    });

    it('selects an icon by clicking it and emits the update, without requiring the user to type a technical name', async () => {
        const wrapper = mount(IconPicker, {
            props: { modelValue: null, 'onUpdate:modelValue': (v: string | null) => wrapper.setProps({ modelValue: v }) },
            attachTo: document.body,
        });

        await wrapper.find('button').trigger('click');
        await wrapper.vm.$nextTick();

        const target = Array.from(document.body.querySelectorAll('button')).find(
            (button) => button.textContent?.includes('Segurança verificada'),
        );
        target?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['shield-check']);

        wrapper.unmount();
    });
});

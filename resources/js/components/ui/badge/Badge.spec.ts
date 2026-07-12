import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Badge from './Badge.vue';

describe('Badge', () => {
    it('renderiza o conteúdo do slot', () => {
        const wrapper = mount(Badge, {
            slots: { default: 'Ativo' },
        });

        expect(wrapper.text()).toBe('Ativo');
    });

    it('aplica a classe do variant informado', () => {
        const wrapper = mount(Badge, {
            props: { variant: 'destructive' },
            slots: { default: 'Inativo' },
        });

        expect(wrapper.attributes('data-slot')).toBe('badge');
    });
});

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AppLogo from './AppLogo.vue';

describe('AppLogo', () => {
    it('renders the full clinic name as a two-tone wordmark', () => {
        const wrapper = mount(AppLogo);

        expect(wrapper.text()).toContain('Gestão de');
        expect(wrapper.text()).toContain('Clínicas');
    });

    it('highlights part of the wordmark with the brand accent color', () => {
        const wrapper = mount(AppLogo);

        expect(wrapper.find('.text-primary').exists()).toBe(true);
        expect(wrapper.find('.text-primary').text()).toBe('Clínicas');
    });

    it('renders the logo mark icon', () => {
        const wrapper = mount(AppLogo);

        expect(wrapper.find('svg').exists()).toBe(true);
    });
});

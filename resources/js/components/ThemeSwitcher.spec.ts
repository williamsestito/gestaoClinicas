import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { h } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import ThemeSwitcher from './ThemeSwitcher.vue';
import { TooltipProvider } from './ui/tooltip';

function mountThemeSwitcher() {
    return mount(TooltipProvider, {
        slots: {
            default: () => h(ThemeSwitcher),
        },
    });
}

describe('ThemeSwitcher', () => {
    beforeEach(() => {
        localStorage.clear();
        // `appearance` dentro do composable é um singleton em nível de
        // módulo (persiste entre montagens) — sem isto, o estado de um
        // teste anterior vaza para o próximo.
        useAppearance().updateAppearance('light');
    });

    it('has an accessible label on the trigger button', () => {
        const wrapper = mountThemeSwitcher();

        expect(
            wrapper.find('button[aria-label="Mudar para tema escuro"]').exists(),
        ).toBe(true);
    });

    it('renders without leftover English text', () => {
        const wrapper = mountThemeSwitcher();

        expect(wrapper.text()).not.toMatch(/\b(Light|Dark|System)\b/);
    });

    it('switches to dark on a single click, with no menu to open first', async () => {
        const wrapper = mountThemeSwitcher();

        expect(document.documentElement.classList.contains('dark')).toBe(false);

        await wrapper.find('button').trigger('click');

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(localStorage.getItem('appearance')).toBe('dark');
    });

    it('switches back to light on a second click', async () => {
        const wrapper = mountThemeSwitcher();

        await wrapper.find('button').trigger('click');
        expect(document.documentElement.classList.contains('dark')).toBe(true);

        await wrapper.find('button').trigger('click');

        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(localStorage.getItem('appearance')).toBe('light');
    });

    it('updates the accessible label to reflect the next action after toggling', async () => {
        const wrapper = mountThemeSwitcher();

        await wrapper.find('button').trigger('click');

        expect(
            wrapper.find('button[aria-label="Mudar para tema claro"]').exists(),
        ).toBe(true);
    });
});

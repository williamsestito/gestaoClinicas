import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { h } from 'vue';
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
    });

    it('has an accessible label on the trigger button', () => {
        const wrapper = mountThemeSwitcher();

        expect(
            wrapper.find('button[aria-label="Alternar tema"]').exists(),
        ).toBe(true);
    });

    it('renders without leftover English text', () => {
        const wrapper = mountThemeSwitcher();

        expect(wrapper.text()).not.toMatch(/\b(Light|Dark|System)\b/);
    });
});

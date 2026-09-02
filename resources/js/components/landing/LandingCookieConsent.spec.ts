import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import LandingCookieConsent from './LandingCookieConsent.vue';

const STORAGE_KEY = 'clinic-cookie-consent';

describe('LandingCookieConsent', () => {
    beforeEach(() => {
        window.localStorage.clear();
    });

    it('shows the banner when no decision was stored yet', async () => {
        const wrapper = mount(LandingCookieConsent);
        await nextTick();

        expect(wrapper.text()).toContain('cookies');
    });

    it('does not show the banner when a decision was already stored', async () => {
        window.localStorage.setItem(STORAGE_KEY, 'accepted');

        const wrapper = mount(LandingCookieConsent);
        await nextTick();

        expect(wrapper.find('[role="region"]').exists()).toBe(false);
    });

    it('persists acceptance and hides the banner when "Aceitar" is clicked', async () => {
        const wrapper = mount(LandingCookieConsent);
        await nextTick();

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Aceitar')!
            .trigger('click');

        expect(window.localStorage.getItem(STORAGE_KEY)).toBe('accepted');
        expect(wrapper.find('[role="region"]').exists()).toBe(false);
    });

    it('persists rejection when "Recusar" is clicked', async () => {
        const wrapper = mount(LandingCookieConsent);
        await nextTick();

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Recusar')!
            .trigger('click');

        expect(window.localStorage.getItem(STORAGE_KEY)).toBe('rejected');
    });
});

import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import LandingNavbar from './LandingNavbar.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>' },
    usePage: () => ({ props: { auth: { user: null } } }),
}));

describe('LandingNavbar', () => {
    it('does not show a FAQ link when faq is not among the active types', () => {
        const wrapper = mount(LandingNavbar, {
            props: {
                title: 'Clínica Exemplo',
                logoUrl: null,
                activeTypes: ['hero', 'about'],
            },
        });

        expect(wrapper.find('a[href="#faq"]').exists()).toBe(false);
    });

    it('shows a FAQ link pointing to #faq when faq is among the active types', () => {
        const wrapper = mount(LandingNavbar, {
            props: {
                title: 'Clínica Exemplo',
                logoUrl: null,
                activeTypes: ['hero', 'faq'],
            },
        });

        const desktopLink = wrapper.find('nav[aria-label="Seções da página"] a[href="#faq"]');
        expect(desktopLink.exists()).toBe(true);
        expect(desktopLink.text()).toBe('Perguntas');
    });

    it('shows the same FAQ link in the mobile menu', async () => {
        const wrapper = mount(LandingNavbar, {
            props: {
                title: 'Clínica Exemplo',
                logoUrl: null,
                activeTypes: ['hero', 'faq'],
            },
            attachTo: document.body,
        });

        await wrapper.find('button[aria-label="Abrir menu"]').trigger('click');
        await wrapper.vm.$nextTick();

        const mobileLink = Array.from(
            document.body.querySelectorAll('a[href="#faq"]'),
        );
        expect(mobileLink.length).toBeGreaterThan(0);

        wrapper.unmount();
    });
});

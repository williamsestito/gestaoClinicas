import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import type { PublicSiteContent } from '@/types/site';
import LandingHeroSection from './LandingHeroSection.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>' },
    usePage: () => ({ props: { auth: { user: null } } }),
}));

function makeSite(overrides: Partial<PublicSiteContent> = {}): PublicSiteContent {
    return {
        title: 'Clínica Essenza',
        description: 'Cuidado que você merece.',
        hero_image_url: null,
        hero_image_mobile_url: null,
        logo_url: null,
        primary_color: null,
        secondary_color: null,
        cta_text: null,
        cta_url: null,
        cta_secondary_text: null,
        cta_secondary_url: null,
        about_text: null,
        facebook_url: null,
        instagram_url: null,
        linkedin_url: null,
        footer_text: null,
        ...overrides,
    };
}

describe('LandingHeroSection', () => {
    it('renders no image container when no banner was uploaded', () => {
        const wrapper = mount(LandingHeroSection, { props: { site: makeSite() } });

        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('renders the desktop banner spanning the full section width, outside the text column', () => {
        const wrapper = mount(LandingHeroSection, {
            props: { site: makeSite({ hero_image_url: '/storage/hero.jpg' }) },
        });

        const img = wrapper.find('img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('/storage/hero.jpg');
        expect(img.classes()).toContain('w-full');
    });

    it('adds a mobile-only <source> when a dedicated mobile banner exists', () => {
        const wrapper = mount(LandingHeroSection, {
            props: {
                site: makeSite({
                    hero_image_url: '/storage/hero-desktop.jpg',
                    hero_image_mobile_url: '/storage/hero-mobile.jpg',
                }),
            },
        });

        const source = wrapper.find('source');
        expect(source.exists()).toBe(true);
        expect(source.attributes('srcset')).toBe('/storage/hero-mobile.jpg');
        expect(source.attributes('media')).toBe('(max-width: 767px)');
    });

    it('falls back to the desktop banner on mobile when no dedicated mobile banner was uploaded', () => {
        const wrapper = mount(LandingHeroSection, {
            props: { site: makeSite({ hero_image_url: '/storage/hero-desktop.jpg' }) },
        });

        expect(wrapper.find('source').exists()).toBe(false);
        expect(wrapper.find('img').attributes('src')).toBe('/storage/hero-desktop.jpg');
    });
});

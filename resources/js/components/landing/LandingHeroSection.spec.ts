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
        schema_type_label: null,
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
        mission_text: null,
        vision_text: null,
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

    it('shows the eyebrow badge when the site has a schema type label', () => {
        const wrapper = mount(LandingHeroSection, {
            props: { site: makeSite({ schema_type_label: 'Clínica médica' }) },
        });

        expect(wrapper.text()).toContain('Clínica médica');
    });

    it('does not show an eyebrow badge when there is no schema type label', () => {
        const wrapper = mount(LandingHeroSection, {
            props: { site: makeSite() },
        });

        expect(wrapper.find('.landing-eyebrow').exists()).toBe(false);
    });

    it('shows up to three quick highlights reused from the benefits list', () => {
        const wrapper = mount(LandingHeroSection, {
            props: {
                site: makeSite(),
                benefits: [
                    { id: 1, icon: null, title: 'Atendimento humanizado', description: null },
                    { id: 2, icon: null, title: 'Agenda online', description: null },
                    { id: 3, icon: null, title: 'Equipe integrada', description: null },
                    { id: 4, icon: null, title: 'Não deveria aparecer', description: null },
                ],
            },
        });

        const items = wrapper.findAll('li');
        expect(items).toHaveLength(3);
        expect(wrapper.text()).toContain('Atendimento humanizado');
        expect(wrapper.text()).not.toContain('Não deveria aparecer');
    });

    it('does not render the highlights list when there are no benefits', () => {
        const wrapper = mount(LandingHeroSection, {
            props: { site: makeSite(), benefits: [] },
        });

        expect(wrapper.find('ul').exists()).toBe(false);
    });
});

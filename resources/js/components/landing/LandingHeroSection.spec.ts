import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import type { PublicSiteContent } from '@/types/site';
import LandingHeroSection from './LandingHeroSection.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>' },
    usePage: () => ({ props: { auth: { user: null } } }),
}));

function makeSite(
    overrides: Partial<PublicSiteContent> = {},
): PublicSiteContent {
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
        const wrapper = mount(LandingHeroSection, {
            props: { site: makeSite() },
        });

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
            props: {
                site: makeSite({ hero_image_url: '/storage/hero-desktop.jpg' }),
            },
        });

        expect(wrapper.find('source').exists()).toBe(false);
        expect(wrapper.find('img').attributes('src')).toBe(
            '/storage/hero-desktop.jpg',
        );
    });

    it('shows the eyebrow badge when the site has a schema type label', () => {
        const wrapper = mount(LandingHeroSection, {
            props: { site: makeSite({ schema_type_label: 'Clínica médica' }) },
        });

        expect(wrapper.text()).toContain('Clínica médica');
    });

    it('hides the eyebrow, title and description visually once a custom banner is uploaded — the banner already carries its own message, and overlaying text on it looked cluttered', () => {
        const wrapper = mount(LandingHeroSection, {
            props: {
                site: makeSite({
                    title: 'Espaço Duda Almeida',
                    description:
                        'Cuidar dos seus pés é cuidar da sua qualidade de vida.',
                    schema_type_label: 'Estética e bem-estar',
                    hero_image_url: '/storage/hero.jpg',
                }),
            },
        });

        expect(wrapper.find('.landing-eyebrow').exists()).toBe(false);
        expect(wrapper.text()).not.toContain(
            'Cuidar dos seus pés é cuidar da sua qualidade de vida.',
        );

        // O <h1> continua no DOM (heading principal da página), só
        // visualmente oculto — nunca removido por completo.
        const heading = wrapper.find('h1');
        expect(heading.exists()).toBe(true);
        expect(heading.text()).toBe('Espaço Duda Almeida');
        expect(heading.classes()).toContain('sr-only');
    });

    it('also hides the CTA button and quick highlights once a custom banner is uploaded — the navbar keeps a functional way to schedule/log in', () => {
        const wrapper = mount(LandingHeroSection, {
            props: {
                site: makeSite({
                    hero_image_url: '/storage/hero.jpg',
                    cta_text: 'Agende sua avaliação',
                    cta_url: 'https://wa.me/554799999999',
                }),
                benefits: [
                    {
                        id: 1,
                        icon: null,
                        title: 'Atendimento humanizado',
                        description: null,
                    },
                ],
            },
        });

        expect(wrapper.text()).not.toContain('Agende sua avaliação');
        expect(wrapper.find('ul').exists()).toBe(false);
    });

    it('shows the eyebrow, title and description normally when there is no custom banner', () => {
        const wrapper = mount(LandingHeroSection, {
            props: {
                site: makeSite({
                    description: 'Cuidado que você merece.',
                    schema_type_label: 'Clínica médica',
                }),
            },
        });

        expect(wrapper.find('.landing-eyebrow').exists()).toBe(true);
        expect(wrapper.text()).toContain('Cuidado que você merece.');
        expect(wrapper.find('h1').classes()).not.toContain('sr-only');
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
                    {
                        id: 1,
                        icon: null,
                        title: 'Atendimento humanizado',
                        description: null,
                    },
                    {
                        id: 2,
                        icon: null,
                        title: 'Agenda online',
                        description: null,
                    },
                    {
                        id: 3,
                        icon: null,
                        title: 'Equipe integrada',
                        description: null,
                    },
                    {
                        id: 4,
                        icon: null,
                        title: 'Não deveria aparecer',
                        description: null,
                    },
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

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
    it('never renders an <img> for the banner — it only exists as a desktop background-image', () => {
        const wrapper = mount(LandingHeroSection, {
            props: { site: makeSite({ hero_image_url: '/storage/hero.jpg' }) },
        });

        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('does not set a background-image style when no banner was uploaded', () => {
        const wrapper = mount(LandingHeroSection, {
            props: { site: makeSite() },
        });

        expect(wrapper.html()).not.toContain('--hero-bg-image');
    });

    it('sets the background-image style from hero_image_url when a banner was uploaded', () => {
        const wrapper = mount(LandingHeroSection, {
            props: {
                site: makeSite({ hero_image_url: '/storage/hero-desktop.jpg' }),
            },
        });

        expect(wrapper.html()).toContain('--hero-bg-image');
        expect(wrapper.html()).toContain('/storage/hero-desktop.jpg');
    });

    it('shows the eyebrow badge when the site has a schema type label', () => {
        const wrapper = mount(LandingHeroSection, {
            props: { site: makeSite({ schema_type_label: 'Clínica médica' }) },
        });

        expect(wrapper.text()).toContain('Clínica médica');
    });

    it('keeps the real title, description and CTA visible for mobile even with a custom banner — only the desktop background carries the banner now', () => {
        const wrapper = mount(LandingHeroSection, {
            props: {
                site: makeSite({
                    title: 'Espaço Duda Almeida',
                    description:
                        'Cuidar dos seus pés é cuidar da sua qualidade de vida.',
                    schema_type_label: 'Estética e bem-estar',
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

        expect(wrapper.find('.landing-eyebrow').exists()).toBe(true);
        expect(wrapper.text()).toContain(
            'Cuidar dos seus pés é cuidar da sua qualidade de vida.',
        );
        expect(wrapper.text()).toContain('Agende sua avaliação');
        expect(wrapper.find('ul').exists()).toBe(true);
    });

    it('marks the title, description, CTA and highlights as desktop-hidden once a custom banner is uploaded — the banner already carries that message from md upward', () => {
        const wrapper = mount(LandingHeroSection, {
            props: {
                site: makeSite({
                    hero_image_url: '/storage/hero.jpg',
                    schema_type_label: 'Estética e bem-estar',
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

        // O <h1> continua no DOM (heading principal da página) e visível no
        // mobile — só vira sr-only a partir do md, nunca removido.
        const heading = wrapper.find('h1');
        expect(heading.exists()).toBe(true);
        expect(heading.classes()).toContain('md:sr-only');
        expect(heading.classes()).not.toContain('sr-only');

        expect(wrapper.find('.landing-eyebrow').classes()).toContain(
            'md:hidden',
        );
        expect(wrapper.find('p').classes()).toContain('md:hidden');
        expect(wrapper.find('ul').classes()).toContain('md:hidden');
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
        expect(wrapper.find('h1').classes()).not.toContain('md:sr-only');
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

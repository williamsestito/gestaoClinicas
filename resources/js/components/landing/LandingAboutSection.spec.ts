import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { PublicSiteContent } from '@/types/site';
import LandingAboutSection from './LandingAboutSection.vue';

function makeSite(overrides: Partial<PublicSiteContent> = {}): PublicSiteContent {
    return {
        title: 'Clínica Essenza',
        description: null,
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

describe('LandingAboutSection', () => {
    it('does not render when there is no about text', () => {
        const wrapper = mount(LandingAboutSection, {
            props: { site: makeSite() },
        });

        expect(wrapper.find('section').exists()).toBe(false);
    });

    it('renders the about text once configured', () => {
        const wrapper = mount(LandingAboutSection, {
            props: { site: makeSite({ about_text: 'Nossa história começou em 2010.' }) },
        });

        expect(wrapper.text()).toContain('Nossa história começou em 2010.');
    });

    it('shows up to three differentiators reused from the benefits list', () => {
        const wrapper = mount(LandingAboutSection, {
            props: {
                site: makeSite({ about_text: 'Texto sobre a clínica.' }),
                benefits: [
                    { id: 1, icon: null, title: 'Atendimento humanizado', description: null },
                    { id: 2, icon: null, title: 'Agenda online', description: null },
                    { id: 3, icon: null, title: 'Equipe integrada', description: null },
                    { id: 4, icon: null, title: 'Não deveria aparecer', description: null },
                ],
            },
        });

        expect(wrapper.findAll('li')).toHaveLength(3);
        expect(wrapper.text()).not.toContain('Não deveria aparecer');
    });

    it('shows mission and vision only when configured', () => {
        const withoutThem = mount(LandingAboutSection, {
            props: { site: makeSite({ about_text: 'Texto sobre a clínica.' }) },
        });
        expect(withoutThem.text()).not.toContain('Missão');
        expect(withoutThem.text()).not.toContain('Visão');

        const withThem = mount(LandingAboutSection, {
            props: {
                site: makeSite({
                    about_text: 'Texto sobre a clínica.',
                    mission_text: 'Cuidar com excelência.',
                    vision_text: 'Ser referência regional.',
                }),
            },
        });
        expect(withThem.text()).toContain('Cuidar com excelência.');
        expect(withThem.text()).toContain('Ser referência regional.');
    });
});

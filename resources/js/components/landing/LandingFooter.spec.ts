import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { PublicSiteContent } from '@/types/site';
import LandingFooter from './LandingFooter.vue';

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

describe('LandingFooter', () => {
    it('shows a navigation column with links to the active sections', () => {
        const wrapper = mount(LandingFooter, {
            props: {
                site: makeSite(),
                contact: null,
                activeTypes: ['hero', 'about', 'services'],
            },
        });

        const nav = wrapper.find('nav[aria-label="Navegação do rodapé"]');
        expect(nav.exists()).toBe(true);
        expect(nav.findAll('a')).toHaveLength(3);
    });

    it('does not show a navigation column when there are no linkable sections', () => {
        const wrapper = mount(LandingFooter, {
            props: { site: makeSite(), contact: null, activeTypes: [] },
        });

        expect(wrapper.find('nav[aria-label="Navegação do rodapé"]').exists()).toBe(false);
    });

    it('shows the contact column only when contact data exists', () => {
        const withoutContact = mount(LandingFooter, {
            props: { site: makeSite(), contact: null, activeTypes: [] },
        });
        expect(withoutContact.text()).not.toContain('Contato');

        const withContact = mount(LandingFooter, {
            props: {
                site: makeSite(),
                contact: {
                    name: 'Clínica Essenza',
                    phone: '4732221122',
                    whatsapp: null,
                    email: null,
                    address: null,
                    opening_hours: [],
                    map_url: null,
                },
                activeTypes: [],
            },
        });
        expect(withContact.text()).toContain('4732221122');
    });

    it('always shows a legal column with the copyright and platform signature', () => {
        const wrapper = mount(LandingFooter, {
            props: { site: makeSite(), contact: null, activeTypes: [] },
        });

        expect(wrapper.text()).toContain('Legal');
        expect(wrapper.text()).toContain('Gestão de Clínicas');
        expect(wrapper.text()).toContain(String(new Date().getFullYear()));
    });
});

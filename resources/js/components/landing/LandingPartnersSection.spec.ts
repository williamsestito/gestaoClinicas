import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import LandingPartnersSection from './LandingPartnersSection.vue';

describe('LandingPartnersSection', () => {
    it('renders nothing when there are no partners', () => {
        const wrapper = mount(LandingPartnersSection, {
            props: { partners: [] },
        });

        expect(wrapper.find('section').exists()).toBe(false);
    });

    it('renders a logo image for each partner', () => {
        const wrapper = mount(LandingPartnersSection, {
            props: {
                partners: [
                    { id: 1, name: 'Convênio A', logo_url: '/storage/a.png', url: null },
                    { id: 2, name: 'Convênio B', logo_url: '/storage/b.png', url: 'https://b.example' },
                ],
            },
        });

        expect(wrapper.findAll('img')).toHaveLength(2);
    });

    it('links to the partner site only when a url is set', () => {
        const wrapper = mount(LandingPartnersSection, {
            props: {
                partners: [
                    { id: 1, name: 'Convênio A', logo_url: null, url: null },
                    { id: 2, name: 'Convênio B', logo_url: null, url: 'https://b.example' },
                ],
            },
        });

        const links = wrapper.findAll('a');
        expect(links).toHaveLength(1);
        expect(links[0]!.attributes('href')).toBe('https://b.example');
    });

    it('falls back to the partner name when there is no logo', () => {
        const wrapper = mount(LandingPartnersSection, {
            props: {
                partners: [
                    { id: 1, name: 'Convênio Sem Logo', logo_url: null, url: null },
                ],
            },
        });

        expect(wrapper.text()).toContain('Convênio Sem Logo');
    });
});

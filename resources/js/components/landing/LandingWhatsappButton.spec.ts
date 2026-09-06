import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import LandingWhatsappButton from './LandingWhatsappButton.vue';

function makeContact(whatsapp: string | null) {
    return {
        name: 'Clínica Essenza',
        phone: null,
        whatsapp,
        email: null,
        address: null,
        opening_hours: [],
        map_url: null,
        latitude: null,
        longitude: null,
    };
}

describe('LandingWhatsappButton', () => {
    it('does not render when there is no contact', () => {
        const wrapper = mount(LandingWhatsappButton, {
            props: { contact: null },
        });

        expect(wrapper.find('a').exists()).toBe(false);
    });

    it('does not render when the clinic has no WhatsApp number registered', () => {
        const wrapper = mount(LandingWhatsappButton, {
            props: { contact: makeContact(null) },
        });

        expect(wrapper.find('a').exists()).toBe(false);
    });

    it('links to wa.me with a prefilled message when a WhatsApp number exists', () => {
        const wrapper = mount(LandingWhatsappButton, {
            props: { contact: makeContact('11999998888') },
        });

        const link = wrapper.find('a');
        expect(link.exists()).toBe(true);
        expect(link.attributes('href')).toContain(
            'https://wa.me/5511999998888',
        );
        expect(link.attributes('href')).toContain('text=');
        expect(link.attributes('aria-label')).toBe('Falar no WhatsApp');
    });
});

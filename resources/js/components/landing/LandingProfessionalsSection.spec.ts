import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { useLandingScheduling } from '@/composables/useLandingScheduling';
import type { PublicProfessional } from '@/types/site';
import LandingProfessionalsSection from './LandingProfessionalsSection.vue';

function makeProfessional(
    overrides: Partial<PublicProfessional> = {},
): PublicProfessional {
    return {
        id: 'site-1',
        professional_id: null,
        name: 'Dra. Ana Souza',
        role_title: null,
        specialty: null,
        professional_register: null,
        bio: null,
        photo_url: null,
        facebook_url: null,
        instagram_url: null,
        linkedin_url: null,
        order: 0,
        ...overrides,
    };
}

beforeEach(() => {
    useLandingScheduling().selectedProfessionalName.value = null;
});

describe('LandingProfessionalsSection', () => {
    it('does not render when there are no professionals', () => {
        const wrapper = mount(LandingProfessionalsSection, {
            props: { professionals: [] },
        });

        expect(wrapper.find('section').exists()).toBe(false);
    });

    it('shows the specialty when configured', () => {
        const wrapper = mount(LandingProfessionalsSection, {
            props: {
                professionals: [makeProfessional({ specialty: 'Ortodontia' })],
            },
        });

        expect(wrapper.text()).toContain('Ortodontia');
    });

    it('sets the shared professional name when "Agendar" is clicked', async () => {
        const wrapper = mount(LandingProfessionalsSection, {
            props: { professionals: [makeProfessional({ name: 'Dr. João' })] },
        });

        await wrapper.find('a[href="#scheduling"]').trigger('click');

        expect(useLandingScheduling().selectedProfessionalName.value).toBe(
            'Dr. João',
        );
    });

    it('shows a placeholder icon when there is no photo', () => {
        const wrapper = mount(LandingProfessionalsSection, {
            props: { professionals: [makeProfessional({ photo_url: null })] },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.find('svg').exists()).toBe(true);
    });
});

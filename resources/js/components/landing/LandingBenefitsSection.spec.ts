import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { PublicBenefit } from '@/types/site';
import LandingBenefitsSection from './LandingBenefitsSection.vue';

function makeBenefit(overrides: Partial<PublicBenefit> = {}): PublicBenefit {
    return {
        id: 1,
        title: 'Atendimento humanizado',
        description: null,
        icon: null,
        ...overrides,
    };
}

describe('LandingBenefitsSection', () => {
    it('does not render the section when there are no benefits', () => {
        const wrapper = mount(LandingBenefitsSection, { props: { benefits: [] } });

        expect(wrapper.find('section').exists()).toBe(false);
    });

    it('resolves a known icon key to its icon component', () => {
        const wrapper = mount(LandingBenefitsSection, {
            props: { benefits: [makeBenefit({ icon: 'shield-check' })] },
        });

        expect(wrapper.find('svg.lucide-shield-check').exists()).toBe(true);
    });

    it('falls back to the default icon for an unknown or missing icon key', () => {
        const wrapper = mount(LandingBenefitsSection, {
            props: {
                benefits: [
                    makeBenefit({ id: 1, icon: 'not-a-real-icon' }),
                    makeBenefit({ id: 2, icon: null }),
                ],
            },
        });

        expect(wrapper.findAll('svg.lucide-heart-handshake').length).toBe(2);
    });

    it('renders the title and description of each benefit', () => {
        const wrapper = mount(LandingBenefitsSection, {
            props: {
                benefits: [
                    makeBenefit({ title: 'Equipe especializada', description: 'Profissionais qualificados.' }),
                ],
            },
        });

        expect(wrapper.text()).toContain('Equipe especializada');
        expect(wrapper.text()).toContain('Profissionais qualificados.');
    });
});

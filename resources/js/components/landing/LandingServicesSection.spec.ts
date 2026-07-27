import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { PublicService } from '@/types/site';
import LandingServicesSection from './LandingServicesSection.vue';

function makeService(overrides: Partial<PublicService> = {}): PublicService {
    return {
        id: 1,
        name: 'Serviço',
        short_description: null,
        description: null,
        image_url: null,
        icon: null,
        category: null,
        duration_minutes: null,
        starting_price_cents: null,
        cta_text: null,
        is_featured: false,
        ...overrides,
    };
}

describe('LandingServicesSection', () => {
    it('does not render when there are no services', () => {
        const wrapper = mount(LandingServicesSection, {
            props: { services: [] },
        });

        expect(wrapper.find('section').exists()).toBe(false);
    });

    it('does not show category filters when no service has a category', () => {
        const wrapper = mount(LandingServicesSection, {
            props: { services: [makeService({ id: 1 }), makeService({ id: 2 })] },
        });

        expect(wrapper.find('[role="tablist"]').exists()).toBe(false);
    });

    it('shows a "Todos" tab plus one per distinct category', () => {
        const wrapper = mount(LandingServicesSection, {
            props: {
                services: [
                    makeService({ id: 1, category: 'Estética' }),
                    makeService({ id: 2, category: 'Odontologia' }),
                    makeService({ id: 3, category: 'Estética' }),
                ],
            },
        });

        const tabs = wrapper.findAll('[role="tab"]');
        expect(tabs.map((t) => t.text())).toEqual(['Todos', 'Estética', 'Odontologia']);
    });

    it('filters services when a category tab is clicked', async () => {
        const wrapper = mount(LandingServicesSection, {
            props: {
                services: [
                    makeService({ id: 1, name: 'Limpeza de pele', category: 'Estética' }),
                    makeService({ id: 2, name: 'Canal', category: 'Odontologia' }),
                ],
            },
        });

        expect(wrapper.text()).toContain('Limpeza de pele');
        expect(wrapper.text()).toContain('Canal');

        const odontologyTab = wrapper
            .findAll('[role="tab"]')
            .find((t) => t.text() === 'Odontologia')!;
        await odontologyTab.trigger('click');

        expect(wrapper.text()).not.toContain('Limpeza de pele');
        expect(wrapper.text()).toContain('Canal');
    });

    it('never shows a price when none was configured', () => {
        const wrapper = mount(LandingServicesSection, {
            props: { services: [makeService({ starting_price_cents: null })] },
        });

        expect(wrapper.text()).not.toContain('R$');
    });

    it('shows the formatted price when configured', () => {
        const wrapper = mount(LandingServicesSection, {
            props: { services: [makeService({ starting_price_cents: 15000 })] },
        });

        expect(wrapper.text()).toContain('150');
    });
});

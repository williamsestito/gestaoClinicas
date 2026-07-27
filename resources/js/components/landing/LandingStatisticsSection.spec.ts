import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import LandingStatisticsSection from './LandingStatisticsSection.vue';

describe('LandingStatisticsSection', () => {
    it('renders nothing when there are no statistics', () => {
        const wrapper = mount(LandingStatisticsSection, {
            props: { statistics: [] },
        });

        expect(wrapper.find('section').exists()).toBe(false);
    });

    it('renders every statistic with its value and label', () => {
        const wrapper = mount(LandingStatisticsSection, {
            props: {
                statistics: [
                    { value: '12', label: 'Profissionais' },
                    { value: '5', label: 'Especialidades' },
                ],
            },
        });

        expect(wrapper.text()).toContain('12');
        expect(wrapper.text()).toContain('Profissionais');
        expect(wrapper.text()).toContain('5');
        expect(wrapper.text()).toContain('Especialidades');
    });
});

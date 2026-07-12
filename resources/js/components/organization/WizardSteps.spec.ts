import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import WizardSteps from './WizardSteps.vue';

describe('WizardSteps', () => {
    it('renders every step label', () => {
        const wrapper = mount(WizardSteps, {
            props: {
                steps: ['Organização', 'Entidade legal', 'Unidade'],
                current: 0,
            },
        });

        expect(wrapper.text()).toContain('Organização');
        expect(wrapper.text()).toContain('Entidade legal');
        expect(wrapper.text()).toContain('Unidade');
    });

    it('marks the current step distinctly from the others', () => {
        const wrapper = mount(WizardSteps, {
            props: { steps: ['Organização', 'Entidade legal'], current: 1 },
        });

        const items = wrapper.findAll('li');
        expect(items[1].classes()).toContain('font-semibold');
        expect(items[0].classes()).not.toContain('font-semibold');
    });
});

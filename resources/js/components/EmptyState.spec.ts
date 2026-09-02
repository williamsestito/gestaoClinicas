import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import EmptyState from './EmptyState.vue';

describe('EmptyState', () => {
    it('renders the title', () => {
        const wrapper = mount(EmptyState, {
            props: { title: 'Nenhuma nova notificação.' },
        });

        expect(wrapper.text()).toContain('Nenhuma nova notificação.');
    });

    it('renders the description when provided', () => {
        const wrapper = mount(EmptyState, {
            props: {
                title: 'Nenhuma unidade cadastrada ainda.',
                description: 'Cadastre a primeira unidade da sua clínica.',
            },
        });

        expect(wrapper.text()).toContain(
            'Cadastre a primeira unidade da sua clínica.',
        );
    });

    it('does not render an action area when no action slot is given', () => {
        const wrapper = mount(EmptyState, {
            props: { title: 'Nenhuma nova notificação.' },
        });

        expect(wrapper.find('[data-test="empty-state-action"]').exists()).toBe(
            false,
        );
    });

    it('renders the action slot when provided', () => {
        const wrapper = mount(EmptyState, {
            props: { title: 'Nenhuma unidade cadastrada ainda.' },
            slots: {
                action: '<button data-test="empty-state-action">Cadastrar primeira unidade</button>',
            },
        });

        expect(wrapper.text()).toContain('Cadastrar primeira unidade');
    });
});

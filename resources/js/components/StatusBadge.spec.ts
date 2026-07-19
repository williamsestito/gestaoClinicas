import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StatusBadge from './StatusBadge.vue';

describe('StatusBadge', () => {
    it('shows "Ativa" for an active record', () => {
        const wrapper = mount(StatusBadge, {
            props: { status: 'active' },
        });

        expect(wrapper.text()).toContain('Ativa');
        expect(wrapper.text()).not.toContain('Inativa');
    });

    it('shows "Inativa" for an inactive record', () => {
        const wrapper = mount(StatusBadge, {
            props: { status: 'inactive' },
        });

        expect(wrapper.text()).toContain('Inativa');
    });

    it('shows "Removida" instead of the status when soft-deleted', () => {
        const wrapper = mount(StatusBadge, {
            props: { status: 'active', deletedAt: '2026-07-19T12:00:00Z' },
        });

        expect(wrapper.text()).toContain('Removida');
        expect(wrapper.text()).not.toContain('Ativa');
    });

    it('renders the highlight badge alongside the status badge', () => {
        const wrapper = mount(StatusBadge, {
            props: { status: 'active', highlightLabel: 'Matriz' },
        });

        expect(wrapper.text()).toContain('Matriz');
        expect(wrapper.text()).toContain('Ativa');
    });

    it('does not render a highlight badge when none is given', () => {
        const wrapper = mount(StatusBadge, {
            props: { status: 'active' },
        });

        expect(wrapper.text()).not.toContain('Matriz');
    });
});

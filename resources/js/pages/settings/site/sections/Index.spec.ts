import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import Index from './Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    useForm: (initial: Record<string, unknown>) =>
        reactive({
            ...initial,
            processing: false,
            put: vi.fn(),
        }),
}));

function makeSections() {
    return [
        { type: 'hero' as const, active: true },
        { type: 'benefits' as const, active: true },
        { type: 'faq' as const, active: false },
    ];
}

describe('settings/site/sections/Index', () => {
    it('renders every section as a compact table row, not a large card per section', () => {
        const wrapper = mount(Index, { props: { sections: makeSections() } });

        const table = wrapper.find('table');
        expect(table.exists()).toBe(true);
        expect(table.findAll('tbody tr')).toHaveLength(3);
        expect(table.text()).toContain('Banner principal');
        expect(table.text()).toContain('Diferenciais');
        expect(table.text()).toContain('Perguntas frequentes');
    });

    it('shows the active/inactive status of each section', () => {
        const wrapper = mount(Index, { props: { sections: makeSections() } });

        const rows = wrapper.findAll('tbody tr');
        expect(rows[0]?.text()).toContain('Ativa');
        expect(rows[2]?.text()).toContain('Inativa');
    });

    it('reorders sections when moving one up', async () => {
        const wrapper = mount(Index, { props: { sections: makeSections() } });

        const moveUpButtons = wrapper.findAll(
            'button[aria-label^="Mover"][aria-label*="para cima"]',
        );
        await moveUpButtons[1]!.trigger('click');

        const rows = wrapper.findAll('tbody tr');
        expect(rows[0]?.text()).toContain('Diferenciais');
        expect(rows[1]?.text()).toContain('Banner principal');
    });

    it('disables moving the first section up and the last section down', () => {
        const wrapper = mount(Index, { props: { sections: makeSections() } });

        const upButtons = wrapper.findAll(
            'button[aria-label*="para cima"]',
        );
        const downButtons = wrapper.findAll(
            'button[aria-label*="para baixo"]',
        );

        expect(upButtons[0]?.attributes('disabled')).toBeDefined();
        expect(downButtons.at(-1)?.attributes('disabled')).toBeDefined();
    });
});

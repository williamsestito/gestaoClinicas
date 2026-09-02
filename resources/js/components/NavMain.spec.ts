import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { h } from 'vue';
import type { NavGroup } from '@/types';
import NavMain from './NavMain.vue';
import { SidebarProvider } from './ui/sidebar';

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    usePage: () => ({ url: '/settings/units' }),
}));

function mountNavMain(groups: NavGroup[]) {
    return mount(SidebarProvider, {
        slots: {
            default: () => h(NavMain, { groups }),
        },
    });
}

async function openGroup(
    wrapper: ReturnType<typeof mountNavMain>,
    title: string,
) {
    const trigger = wrapper
        .findAll('button')
        .find((button) => button.text().includes(title));
    await trigger?.trigger('click');
}

describe('NavMain', () => {
    it('always renders the group title, and renders item labels once expanded', async () => {
        const wrapper = mountNavMain([
            {
                title: 'Visão Geral',
                items: [{ title: 'Dashboard', href: '/dashboard' }],
            },
        ]);

        expect(wrapper.text()).toContain('Visão Geral');

        await openGroup(wrapper, 'Visão Geral');

        expect(wrapper.text()).toContain('Dashboard');
    });

    it('keeps the group containing the current page expanded by default', () => {
        const wrapper = mountNavMain([
            {
                title: 'Operação',
                items: [{ title: 'Agenda', href: '#', disabled: true }],
            },
            {
                title: 'Clínica',
                items: [{ title: 'Unidades', href: '/settings/units' }],
            },
        ]);

        expect(wrapper.text()).toContain('Unidades');
    });

    it('renders disabled items as non-navigable and marked "em breve" once expanded', async () => {
        const wrapper = mountNavMain([
            {
                title: 'Operação',
                items: [{ title: 'Agenda', href: '#', disabled: true }],
            },
        ]);

        await openGroup(wrapper, 'Operação');

        expect(wrapper.text()).toContain('Em breve');
        expect(wrapper.find('a[href="#"]').exists()).toBe(false);

        const disabledButton = wrapper.find('button[disabled]');
        expect(disabledButton.exists()).toBe(true);
        expect(disabledButton.attributes('aria-disabled')).toBe('true');
    });
});

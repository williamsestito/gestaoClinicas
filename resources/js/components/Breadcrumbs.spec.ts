import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Breadcrumbs from './Breadcrumbs.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
}));

describe('Breadcrumbs', () => {
    it('renders every item as text, in order', () => {
        const wrapper = mount(Breadcrumbs, {
            props: {
                breadcrumbs: [
                    { title: 'Início', href: '/dashboard' },
                    { title: 'Configurações da clínica' },
                    { title: 'Unidades' },
                ],
            },
        });

        expect(wrapper.text()).toContain('Início');
        expect(wrapper.text()).toContain('Configurações da clínica');
        expect(wrapper.text()).toContain('Unidades');
    });

    it('renders a navigable link for items with href', () => {
        const wrapper = mount(Breadcrumbs, {
            props: {
                breadcrumbs: [
                    { title: 'Início', href: '/dashboard' },
                    { title: 'Unidades' },
                ],
            },
        });

        const link = wrapper.find('a[href="/dashboard"]');
        expect(link.exists()).toBe(true);
        expect(link.text()).toBe('Início');
    });

    it('renders an intermediate item without href as plain, non-navigable text', () => {
        const wrapper = mount(Breadcrumbs, {
            props: {
                breadcrumbs: [
                    { title: 'Início', href: '/dashboard' },
                    { title: 'Configurações da clínica' },
                    { title: 'Unidades', href: '/settings/units' },
                ],
            },
        });

        const links = wrapper.findAll('a');
        expect(links.map((link) => link.text())).not.toContain(
            'Configurações da clínica',
        );
    });

    it('renders the last item as the current page, not a link', () => {
        const wrapper = mount(Breadcrumbs, {
            props: {
                breadcrumbs: [
                    { title: 'Início', href: '/dashboard' },
                    { title: 'Unidades', href: '/settings/units' },
                ],
            },
        });

        const links = wrapper.findAll('a');
        expect(links.map((link) => link.text())).not.toContain('Unidades');
    });
});

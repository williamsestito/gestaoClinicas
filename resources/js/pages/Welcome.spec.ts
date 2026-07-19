import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Welcome from './Welcome.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>', props: ['href'] },
}));

function mountWelcome(
    props: Record<string, unknown> = {},
    authUser: unknown = null,
) {
    return mount(Welcome, {
        props: { organizationConfigured: true, ...props },
        global: {
            mocks: {
                $page: { props: { auth: { user: authUser } } },
            },
        },
    });
}

describe('Welcome', () => {
    it('falls back to the default title and description when no site content is configured', () => {
        const wrapper = mountWelcome({ site: null });

        expect(wrapper.text()).toContain('Gestão de Clínicas');
        expect(wrapper.text()).toContain('está em desenvolvimento');
    });

    it('renders the administered title and description when provided', () => {
        const wrapper = mountWelcome({
            site: {
                title: 'Clínica Exemplo',
                description: 'Cuidando de você com excelência.',
                hero_image_url: null,
                primary_color: '#0F766E',
                secondary_color: '#F59E0B',
            },
        });

        expect(wrapper.text()).toContain('Clínica Exemplo');
        expect(wrapper.text()).toContain('Cuidando de você com excelência.');
    });

    it('renders the hero image only when a URL is provided', () => {
        const withImage = mountWelcome({
            site: {
                title: 'Clínica Exemplo',
                description: null,
                hero_image_url: 'https://example.test/storage/hero.jpg',
                primary_color: null,
                secondary_color: null,
            },
        });
        expect(withImage.find('img').exists()).toBe(true);

        const withoutImage = mountWelcome({ site: null });
        expect(withoutImage.find('img').exists()).toBe(false);
    });

    it('shows login/register links for a guest', () => {
        const wrapper = mountWelcome({ site: null }, null);

        expect(wrapper.text()).toContain('Entrar');
        expect(wrapper.text()).toContain('Criar conta');
        expect(wrapper.text()).not.toContain('Acessar dashboard');
    });

    it('shows the dashboard link for an authenticated user', () => {
        const wrapper = mountWelcome({ site: null }, { id: '1', name: 'Ana' });

        expect(wrapper.text()).toContain('Acessar dashboard');
        expect(wrapper.text()).not.toContain('Entrar');
    });

    it('shows a distinct pending-setup state when no organization is configured yet', () => {
        const wrapper = mountWelcome({
            organizationConfigured: false,
            site: {
                title: 'Deveria ser ignorado',
                description: null,
                hero_image_url: 'https://example.test/hero.jpg',
                primary_color: '#123456',
                secondary_color: '#654321',
            },
        });

        expect(wrapper.text()).toContain('Ambiente em configuração');
        expect(wrapper.text()).not.toContain('Deveria ser ignorado');
        // Sem organização, não há como confiar na imagem/cores administradas.
        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('still offers a login link in the pending-setup state', () => {
        const wrapper = mountWelcome({ organizationConfigured: false }, null);

        expect(wrapper.text()).toContain('Entrar');
    });

    it('has exactly one h1 and the landmark structure required for accessibility', () => {
        const wrapper = mountWelcome();

        expect(wrapper.findAll('h1')).toHaveLength(1);
        expect(wrapper.find('header').exists()).toBe(true);
        expect(wrapper.find('main').exists()).toBe(true);
        expect(wrapper.find('footer').exists()).toBe(true);
    });

    it('renders a keyboard-accessible skip link pointing at the main content', () => {
        const wrapper = mountWelcome();

        const skipLink = wrapper.find('a[href="#main-content"]');
        expect(skipLink.exists()).toBe(true);
        expect(skipLink.text()).toBe('Pular para o conteúdo principal');
        expect(wrapper.find('main#main-content').exists()).toBe(true);
    });
});

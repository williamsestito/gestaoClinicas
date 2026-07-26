import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import LandingFooter from '@/components/landing/LandingFooter.vue';
import LandingNavbar from '@/components/landing/LandingNavbar.vue';
import LandingSectionRenderer from '@/components/landing/LandingSectionRenderer.vue';
import type { LandingSection, PublicSiteContent } from '@/types/site';
import Welcome from './Welcome.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>', props: ['href'] },
    usePage: () => ({ props: { auth: { user: null } } }),
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
            stubs: {
                LandingNavbar: true,
                LandingSectionRenderer: true,
                LandingFooter: true,
            },
        },
    });
}

function makeSite(
    overrides: Partial<PublicSiteContent> = {},
): PublicSiteContent {
    return {
        title: 'Clínica Essenza',
        description: 'Cuidando de você com excelência.',
        hero_image_url: null,
        logo_url: null,
        primary_color: '#0F766E',
        secondary_color: '#F59E0B',
        cta_text: null,
        cta_url: null,
        cta_secondary_text: null,
        cta_secondary_url: null,
        about_text: null,
        facebook_url: null,
        instagram_url: null,
        linkedin_url: null,
        footer_text: null,
        ...overrides,
    };
}

describe('Welcome — fallback state (no site or not published)', () => {
    it('falls back to the default title and description when no site content is configured', () => {
        const wrapper = mountWelcome({ site: null });

        expect(wrapper.text()).toContain('Gestão de Clínicas');
        expect(wrapper.text()).toContain('está em desenvolvimento');
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
            site: null,
        });

        expect(wrapper.text()).toContain('Ambiente em configuração');
    });

    it('still offers a login link in the pending-setup state', () => {
        const wrapper = mountWelcome(
            { organizationConfigured: false, site: null },
            null,
        );

        expect(wrapper.text()).toContain('Entrar');
    });

    it('has exactly one h1 and the landmark structure required for accessibility', () => {
        const wrapper = mountWelcome({ site: null });

        expect(wrapper.findAll('h1')).toHaveLength(1);
        expect(wrapper.find('header').exists()).toBe(true);
        expect(wrapper.find('main').exists()).toBe(true);
        expect(wrapper.find('footer').exists()).toBe(true);
    });

    it('renders a keyboard-accessible skip link pointing at the main content', () => {
        const wrapper = mountWelcome({ site: null });

        const skipLink = wrapper.find('a[href="#main-content"]');
        expect(skipLink.exists()).toBe(true);
        expect(skipLink.text()).toBe('Pular para o conteúdo principal');
        expect(wrapper.find('main#main-content').exists()).toBe(true);
    });
});

describe('Welcome — published landing state', () => {
    const sections: LandingSection[] = [
        { type: 'hero', active: true },
        { type: 'benefits', active: false },
        { type: 'about', active: true },
        { type: 'services', active: true },
        { type: 'scheduling', active: true },
    ];

    it('renders the navbar, an ordered section renderer per active section, and the footer', () => {
        const wrapper = mountWelcome({ site: makeSite(), sections });

        expect(wrapper.findComponent(LandingNavbar).exists()).toBe(true);
        expect(wrapper.findComponent(LandingFooter).exists()).toBe(true);

        const renderers = wrapper.findAllComponents(LandingSectionRenderer);
        expect(renderers.map((r) => r.props('type'))).toEqual([
            'hero',
            'about',
            'services',
            'scheduling',
        ]);
    });

    it('never renders a section renderer for an inactive section', () => {
        const wrapper = mountWelcome({ site: makeSite(), sections });

        const types = wrapper
            .findAllComponents(LandingSectionRenderer)
            .map((r) => r.props('type'));
        expect(types).not.toContain('benefits');
    });

    it('tells the navbar whether the scheduling section is active', () => {
        const wrapper = mountWelcome({ site: makeSite(), sections });

        expect(
            wrapper.findComponent(LandingNavbar).props('activeTypes'),
        ).toContain('scheduling');
    });

    it('does not render the fallback pending-setup markup once a site is published', () => {
        const wrapper = mountWelcome({ site: makeSite(), sections });

        expect(wrapper.text()).not.toContain('está em desenvolvimento');
        expect(wrapper.find('a[href="#main-content"]').exists()).toBe(true);
    });
});

describe('Welcome — FAQ section requires actual content', () => {
    const sectionsWithFaqActive: LandingSection[] = [
        { type: 'hero', active: true },
        { type: 'faq', active: true },
    ];

    it('never links or renders the faq section when it is active but has no faqs', () => {
        const wrapper = mountWelcome({
            site: makeSite(),
            sections: sectionsWithFaqActive,
            faqs: [],
        });

        const types = wrapper
            .findAllComponents(LandingSectionRenderer)
            .map((r) => r.props('type'));
        expect(types).not.toContain('faq');
        expect(
            wrapper.findComponent(LandingNavbar).props('activeTypes'),
        ).not.toContain('faq');
    });

    it('links and renders the faq section once it is active and has faqs', () => {
        const wrapper = mountWelcome({
            site: makeSite(),
            sections: sectionsWithFaqActive,
            faqs: [{ id: 1, question: 'Q?', answer: 'A.', category: null }],
        });

        const types = wrapper
            .findAllComponents(LandingSectionRenderer)
            .map((r) => r.props('type'));
        expect(types).toContain('faq');
        expect(
            wrapper.findComponent(LandingNavbar).props('activeTypes'),
        ).toContain('faq');
    });
});

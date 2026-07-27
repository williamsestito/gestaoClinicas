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
                LandingWhatsappButton: true,
                LandingCookieConsent: true,
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
        schema_type_label: null,
        hero_image_url: null,
        hero_image_mobile_url: null,
        logo_url: null,
        primary_color: '#0F766E',
        secondary_color: '#F59E0B',
        cta_text: null,
        cta_url: null,
        cta_secondary_text: null,
        cta_secondary_url: null,
        about_text: null,
        mission_text: null,
        vision_text: null,
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
    const siteWithAbout = makeSite({ about_text: 'Sobre a clínica.' });
    const oneService = [
        {
            id: 1,
            name: 'Limpeza de pele',
            short_description: null,
            description: null,
            image_url: null,
            icon: null,
            category: null,
            duration_minutes: null,
            starting_price_cents: null,
            cta_text: null,
            is_featured: false,
        },
    ];

    it('renders the navbar, an ordered section renderer per active section, and the footer', () => {
        const wrapper = mountWelcome({
            site: siteWithAbout,
            sections,
            services: oneService,
        });

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
        const wrapper = mountWelcome({
            site: siteWithAbout,
            sections,
            services: oneService,
        });

        const types = wrapper
            .findAllComponents(LandingSectionRenderer)
            .map((r) => r.props('type'));
        expect(types).not.toContain('benefits');
    });

    it('tells the navbar whether the scheduling section is active', () => {
        const wrapper = mountWelcome({
            site: siteWithAbout,
            sections,
            services: oneService,
        });

        expect(
            wrapper.findComponent(LandingNavbar).props('activeTypes'),
        ).toContain('scheduling');
    });

    it('does not render the fallback pending-setup markup once a site is published', () => {
        const wrapper = mountWelcome({
            site: siteWithAbout,
            sections,
            services: oneService,
        });

        expect(wrapper.text()).not.toContain('está em desenvolvimento');
        expect(wrapper.find('a[href="#main-content"]').exists()).toBe(true);
    });
});

describe('Welcome — sections with possibly-empty content require actual content', () => {
    it('never links or renders about/benefits/services/professionals/gallery/testimonials when their content is empty', () => {
        const sections: LandingSection[] = [
            { type: 'hero', active: true },
            { type: 'about', active: true },
            { type: 'benefits', active: true },
            { type: 'services', active: true },
            { type: 'professionals', active: true },
            { type: 'gallery', active: true },
            { type: 'testimonials', active: true },
        ];

        const wrapper = mountWelcome({
            site: makeSite({ about_text: null }),
            sections,
            benefits: [],
            services: [],
            professionals: [],
            gallery: [],
            testimonials: [],
        });

        const types = wrapper
            .findAllComponents(LandingSectionRenderer)
            .map((r) => r.props('type'));
        const activeTypes = wrapper
            .findComponent(LandingNavbar)
            .props('activeTypes') as string[];

        for (const emptyType of [
            'about',
            'benefits',
            'services',
            'professionals',
            'gallery',
            'testimonials',
        ]) {
            expect(types).not.toContain(emptyType);
            expect(activeTypes).not.toContain(emptyType);
        }
    });

    it('links and renders those sections once each has actual content', () => {
        const sections: LandingSection[] = [
            { type: 'hero', active: true },
            { type: 'about', active: true },
            { type: 'services', active: true },
        ];

        const wrapper = mountWelcome({
            site: makeSite({ about_text: 'Sobre a clínica.' }),
            sections,
            services: [
                {
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
                },
            ],
        });

        const types = wrapper
            .findAllComponents(LandingSectionRenderer)
            .map((r) => r.props('type'));
        expect(types).toContain('about');
        expect(types).toContain('services');
    });

    it('never links or renders the contact section when there is no contact info at all', () => {
        const wrapper = mountWelcome({
            site: makeSite(),
            sections: [
                { type: 'hero', active: true },
                { type: 'contact', active: true },
            ],
            contact: {
                name: 'Clínica Exemplo',
                phone: null,
                whatsapp: null,
                email: null,
                address: null,
                opening_hours: [],
                map_url: null,
            },
        });

        const types = wrapper
            .findAllComponents(LandingSectionRenderer)
            .map((r) => r.props('type'));
        expect(types).not.toContain('contact');
        expect(
            wrapper.findComponent(LandingNavbar).props('activeTypes'),
        ).not.toContain('contact');
    });

    it('links and renders the contact section once there is at least a phone number', () => {
        const wrapper = mountWelcome({
            site: makeSite(),
            sections: [
                { type: 'hero', active: true },
                { type: 'contact', active: true },
            ],
            contact: {
                name: 'Clínica Exemplo',
                phone: '(47) 3222-1122',
                whatsapp: null,
                email: null,
                address: null,
                opening_hours: [],
                map_url: null,
            },
        });

        const types = wrapper
            .findAllComponents(LandingSectionRenderer)
            .map((r) => r.props('type'));
        expect(types).toContain('contact');
        expect(
            wrapper.findComponent(LandingNavbar).props('activeTypes'),
        ).toContain('contact');
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

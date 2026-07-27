import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import LandingBenefitsSection from '@/components/landing/LandingBenefitsSection.vue';
import LandingContactSection from '@/components/landing/LandingContactSection.vue';
import LandingFaqSection from '@/components/landing/LandingFaqSection.vue';
import LandingHeroSection from '@/components/landing/LandingHeroSection.vue';
import LandingPartnersSection from '@/components/landing/LandingPartnersSection.vue';
import LandingStatisticsSection from '@/components/landing/LandingStatisticsSection.vue';
import type { LandingSectionType, PublicSiteContent } from '@/types/site';
import LandingSectionRenderer from './LandingSectionRenderer.vue';

function makeSite(): PublicSiteContent {
    return {
        title: 'Clínica Essenza',
        description: null,
        schema_type_label: null,
        hero_image_url: null,
        hero_image_mobile_url: null,
        logo_url: null,
        primary_color: null,
        secondary_color: null,
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
    };
}

const stubs = {
    LandingHeroSection: true,
    LandingBenefitsSection: true,
    LandingAboutSection: true,
    LandingServicesSection: true,
    LandingProfessionalsSection: true,
    LandingGallerySection: true,
    LandingTestimonialsSection: true,
    LandingCtaSection: true,
    LandingSchedulingSection: true,
    LandingContactSection: true,
    LandingFaqSection: true,
    LandingStatisticsSection: true,
    LandingPartnersSection: true,
};

async function mountRenderer(
    type: LandingSectionType,
    extra: Record<string, unknown> = {},
) {
    const wrapper = mount(LandingSectionRenderer, {
        props: {
            type,
            site: makeSite(),
            contact: null,
            benefits: [],
            services: [],
            professionals: [],
            gallery: [],
            testimonials: [],
            faqs: [],
            partners: [],
            statistics: [],
            schedulingActive: false,
            ...extra,
        },
        global: { stubs },
    });

    // As seções são carregadas sob demanda (defineAsyncComponent) — é
    // preciso esperar a promise do import() resolver antes de inspecionar
    // a árvore de componentes renderizada.
    await flushPromises();

    return wrapper;
}

describe('LandingSectionRenderer', () => {
    it('renders the hero component only receiving the site prop it declares', async () => {
        const wrapper = await mountRenderer('hero');

        const hero = wrapper.findComponent(LandingHeroSection);
        expect(hero.exists()).toBe(true);
        expect(hero.props('site').title).toBe('Clínica Essenza');
    });

    it('renders the benefits component with the benefits list', async () => {
        const wrapper = await mountRenderer('benefits', {
            benefits: [
                { id: 1, icon: null, title: 'Rápido', description: null },
            ],
        });

        const benefits = wrapper.findComponent(LandingBenefitsSection);
        expect(benefits.props('benefits')).toHaveLength(1);
    });

    it('renders nothing for a type outside the known catalog, without throwing', async () => {
        await expect(
            mountRenderer('a-type-that-does-not-exist' as LandingSectionType),
        ).resolves.not.toThrow();

        const wrapper = await mountRenderer(
            'a-type-that-does-not-exist' as LandingSectionType,
        );
        expect(wrapper.html()).toBe('<!--v-if-->');
    });

    it('never renders the contact section when there is no contact data, even if the type is active', async () => {
        const wrapper = await mountRenderer('contact', { contact: null });

        expect(wrapper.findComponent(LandingContactSection).exists()).toBe(
            false,
        );
    });

    it('renders the contact section once contact data is available', async () => {
        const wrapper = await mountRenderer('contact', {
            contact: {
                name: 'Clínica Essenza',
                phone: '4732221122',
                whatsapp: null,
                email: null,
                address: null,
                opening_hours: [],
                map_url: null,
            },
        });

        expect(wrapper.findComponent(LandingContactSection).exists()).toBe(
            true,
        );
    });

    it('renders the faq component with the faqs list', async () => {
        const wrapper = await mountRenderer('faq', {
            faqs: [{ id: 1, question: 'Q?', answer: 'A.', category: null }],
        });

        expect(
            wrapper.findComponent(LandingFaqSection).props('faqs'),
        ).toHaveLength(1);
    });

    it('renders the statistics component with the statistics list', async () => {
        const wrapper = await mountRenderer('statistics', {
            statistics: [{ value: '5', label: 'Profissionais' }],
        });

        expect(
            wrapper.findComponent(LandingStatisticsSection).props('statistics'),
        ).toHaveLength(1);
    });

    it('renders the partners component with the partners list', async () => {
        const wrapper = await mountRenderer('partners', {
            partners: [{ id: 1, name: 'Convênio X', logo_url: null, url: null }],
        });

        expect(
            wrapper.findComponent(LandingPartnersSection).props('partners'),
        ).toHaveLength(1);
    });
});

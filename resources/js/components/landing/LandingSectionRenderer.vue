<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import type {
    LandingSectionType,
    PublicBenefit,
    PublicContact,
    PublicFaq,
    PublicGalleryItem,
    PublicPartner,
    PublicProfessional,
    PublicService,
    PublicSiteContent,
    PublicStatistic,
    PublicTestimonial,
} from '@/types/site';

const props = defineProps<{
    type: LandingSectionType;
    site: PublicSiteContent;
    contact: PublicContact | null;
    benefits: PublicBenefit[];
    services: PublicService[];
    professionals: PublicProfessional[];
    gallery: PublicGalleryItem[];
    testimonials: PublicTestimonial[];
    faqs: PublicFaq[];
    partners: PublicPartner[];
    statistics: PublicStatistic[];
    schedulingActive: boolean;
}>();

// Cada seção é um chunk carregado sob demanda (só quando esse tipo
// realmente aparece na página) e recebe exatamente os dados que declara em
// seus próprios props — evita vazar atributos desconhecidos para o DOM e
// evita uma cadeia de v-if/v-else-if extensa no template.
const SECTION_COMPONENTS: Record<
    LandingSectionType,
    { component: unknown; props: () => Record<string, unknown> }
> = {
    hero: {
        component: defineAsyncComponent(
            () => import('@/components/landing/LandingHeroSection.vue'),
        ),
        props: () => ({ site: props.site, benefits: props.benefits }),
    },
    benefits: {
        component: defineAsyncComponent(
            () => import('@/components/landing/LandingBenefitsSection.vue'),
        ),
        props: () => ({ benefits: props.benefits }),
    },
    about: {
        component: defineAsyncComponent(
            () => import('@/components/landing/LandingAboutSection.vue'),
        ),
        props: () => ({ site: props.site, benefits: props.benefits }),
    },
    services: {
        component: defineAsyncComponent(
            () => import('@/components/landing/LandingServicesSection.vue'),
        ),
        props: () => ({ services: props.services }),
    },
    professionals: {
        component: defineAsyncComponent(
            () =>
                import('@/components/landing/LandingProfessionalsSection.vue'),
        ),
        props: () => ({ professionals: props.professionals }),
    },
    gallery: {
        component: defineAsyncComponent(
            () => import('@/components/landing/LandingGallerySection.vue'),
        ),
        props: () => ({ items: props.gallery }),
    },
    testimonials: {
        component: defineAsyncComponent(
            () => import('@/components/landing/LandingTestimonialsSection.vue'),
        ),
        props: () => ({ testimonials: props.testimonials }),
    },
    cta: {
        component: defineAsyncComponent(
            () => import('@/components/landing/LandingCtaSection.vue'),
        ),
        props: () => ({
            site: props.site,
            contact: props.contact,
            showScheduling: props.schedulingActive,
        }),
    },
    scheduling: {
        component: defineAsyncComponent(
            () => import('@/components/landing/LandingSchedulingSection.vue'),
        ),
        props: () => ({}),
    },
    contact: {
        component: defineAsyncComponent(
            () => import('@/components/landing/LandingContactSection.vue'),
        ),
        props: () => ({ contact: props.contact }),
    },
    faq: {
        component: defineAsyncComponent(
            () => import('@/components/landing/LandingFaqSection.vue'),
        ),
        props: () => ({ faqs: props.faqs }),
    },
    statistics: {
        component: defineAsyncComponent(
            () => import('@/components/landing/LandingStatisticsSection.vue'),
        ),
        props: () => ({ statistics: props.statistics }),
    },
    partners: {
        component: defineAsyncComponent(
            () => import('@/components/landing/LandingPartnersSection.vue'),
        ),
        props: () => ({ partners: props.partners }),
    },
};

const entry = computed(() => SECTION_COMPONENTS[props.type] ?? null);
</script>

<template>
    <component
        :is="entry.component"
        v-if="entry && (type !== 'contact' || contact)"
        v-bind="entry.props()"
    />
</template>

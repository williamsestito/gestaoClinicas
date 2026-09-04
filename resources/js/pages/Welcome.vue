<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LandingCookieConsent from '@/components/landing/LandingCookieConsent.vue';
import LandingFooter from '@/components/landing/LandingFooter.vue';
import LandingNavbar from '@/components/landing/LandingNavbar.vue';
import LandingSectionRenderer from '@/components/landing/LandingSectionRenderer.vue';
import LandingWhatsappButton from '@/components/landing/LandingWhatsappButton.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import type {
    LandingSection,
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

const props = withDefaults(
    defineProps<{
        site?: PublicSiteContent | null;
        organizationConfigured: boolean;
        sections?: LandingSection[];
        benefits?: PublicBenefit[];
        services?: PublicService[];
        professionals?: PublicProfessional[];
        gallery?: PublicGalleryItem[];
        testimonials?: PublicTestimonial[];
        faqs?: PublicFaq[];
        partners?: PublicPartner[];
        statistics?: PublicStatistic[];
        contact?: PublicContact | null;
    }>(),
    {
        site: null,
        sections: () => [],
        benefits: () => [],
        services: () => [],
        professionals: () => [],
        gallery: () => [],
        testimonials: () => [],
        faqs: () => [],
        partners: () => [],
        statistics: () => [],
        contact: null,
    },
);

// Cores da clínica sobrescrevem os fallbacks de `.landing-theme` (ver
// resources/css/app.css) só quando cadastradas — nunca um hex fixo aqui.
const landingThemeStyle = computed(() => ({
    '--landing-primary': props.site?.primary_color ?? undefined,
    '--landing-primary-dark': props.site?.secondary_color ?? undefined,
}));

// Estado "fallback": organização ainda não configurada, ou site existente
// mas ainda não publicado — em ambos os casos não há conteúdo administrado
// para exibir, então a página mostra uma mensagem institucional mínima em
// vez da landing completa.
const fallbackTitle = computed(() =>
    props.organizationConfigured
        ? 'Gestão de Clínicas'
        : 'Ambiente em configuração',
);

const fallbackDescription = computed(() => {
    if (!props.organizationConfigured) {
        return 'Esta clínica ainda não concluiu a configuração inicial. Assim que um administrador finalizar o cadastro, esta página passará a apresentar as informações da clínica.';
    }

    return 'Esta aplicação está em desenvolvimento. A fundação técnica está ativa; os módulos de negócio serão adicionados nas próximas fases.';
});

// Seções cujo conteúdo pode estar vazio (ex.: nenhum benefício ativo, sem
// texto "sobre", sem endereço/telefone/e-mail cadastrados) só aparecem —
// inclusive na navbar, que deriva seus links diretamente destas seções —
// quando existe algo de fato para mostrar. Uma seção "ativa" sem conteúdo
// geraria um link para uma âncora vazia ou inexistente na página. Fonte
// única: cada componente de seção já se autoguarda com a mesma condição
// (ver Landing*Section.vue), então isso apenas evita que a navbar fique
// dessincronizada do que realmente é renderizado.
const orderedActiveSections = computed(() =>
    props.sections.filter((section) => {
        if (!section.active) {
            return false;
        }

        switch (section.type) {
            case 'about':
                return Boolean(props.site?.about_text);
            case 'benefits':
                return props.benefits.length > 0;
            case 'services':
                return props.services.length > 0;
            case 'professionals':
                return props.professionals.length > 0;
            case 'gallery':
                return props.gallery.length > 0;
            case 'testimonials':
                return props.testimonials.length > 0;
            case 'faq':
                return props.faqs.length > 0;
            case 'partners':
                return props.partners.length > 0;
            case 'statistics':
                return props.statistics.length > 0;
            case 'contact':
                return (
                    props.contact !== null &&
                    Boolean(
                        props.contact.phone ||
                        props.contact.email ||
                        props.contact.address ||
                        props.contact.opening_hours.length > 0 ||
                        props.contact.map_url,
                    )
                );
            default:
                return true;
        }
    }),
);

const schedulingActive = computed(() =>
    orderedActiveSections.value.some(
        (section) => section.type === 'scheduling',
    ),
);
</script>

<template>
    <Head :title="site?.title ?? fallbackTitle" />

    <a
        href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-primary-foreground focus:shadow-lg focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
    >
        Pular para o conteúdo principal
    </a>

    <div
        v-if="!site"
        class="landing-theme flex min-h-screen flex-col bg-background text-foreground"
    >
        <header class="flex justify-center px-6 pt-10">
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-primary text-primary-foreground"
            >
                <AppLogoIcon class="size-7 fill-current" aria-hidden="true" />
                <span class="sr-only">{{ fallbackTitle }}</span>
            </div>
        </header>

        <main
            id="main-content"
            tabindex="-1"
            class="flex flex-1 flex-col items-center justify-center gap-8 px-6 py-12 text-center outline-none"
        >
            <div class="flex w-full max-w-2xl flex-col items-center gap-8">
                <div class="space-y-3">
                    <h1
                        class="text-3xl font-bold tracking-tight text-balance sm:text-4xl"
                    >
                        {{ fallbackTitle }}
                    </h1>
                    <p
                        class="text-base text-balance text-muted-foreground sm:text-lg"
                    >
                        {{ fallbackDescription }}
                    </p>
                </div>

                <div
                    class="flex w-full max-w-xs flex-col gap-3 sm:flex-row sm:justify-center"
                >
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="w-full"
                    >
                        <Button class="w-full">Acessar dashboard</Button>
                    </Link>
                    <template v-else>
                        <Link :href="login()" class="w-full">
                            <Button class="w-full">Entrar</Button>
                        </Link>
                        <Link :href="register()" class="w-full">
                            <Button variant="outline" class="w-full"
                                >Criar conta</Button
                            >
                        </Link>
                    </template>
                </div>
            </div>
        </main>

        <footer class="px-6 pb-8 text-center text-xs text-muted-foreground">
            <p>© {{ new Date().getFullYear() }} {{ fallbackTitle }}</p>
        </footer>
    </div>

    <div
        v-else
        class="landing-theme flex min-h-screen flex-col bg-background text-foreground"
        :style="landingThemeStyle"
    >
        <LandingNavbar
            :title="site.title"
            :logo-url="site.logo_url"
            :active-types="orderedActiveSections.map((section) => section.type)"
        />

        <main id="main-content" tabindex="-1" class="outline-none">
            <LandingSectionRenderer
                v-for="section in orderedActiveSections"
                :key="section.type"
                :type="section.type"
                :site="site"
                :contact="contact"
                :benefits="benefits"
                :services="services"
                :professionals="professionals"
                :gallery="gallery"
                :testimonials="testimonials"
                :faqs="faqs"
                :partners="partners"
                :statistics="statistics"
                :scheduling-active="schedulingActive"
            />
        </main>

        <LandingFooter
            :site="site"
            :contact="contact"
            :active-types="orderedActiveSections.map((section) => section.type)"
        />

        <LandingWhatsappButton :contact="contact" />
        <LandingCookieConsent />
    </div>
</template>

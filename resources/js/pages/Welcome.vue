<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

type SiteContent = {
    title: string;
    description: string | null;
    hero_image_url: string | null;
    primary_color: string | null;
    secondary_color: string | null;
    og_image_alt?: string | null;
};

const props = defineProps<{
    site?: SiteContent | null;
    organizationConfigured: boolean;
}>();

const title = computed(() => {
    if (!props.organizationConfigured) {
        return 'Ambiente em configuração';
    }

    return props.site?.title || 'Gestão de Clínicas';
});

const description = computed(() => {
    if (!props.organizationConfigured) {
        return 'Esta clínica ainda não concluiu a configuração inicial. Assim que um administrador finalizar o cadastro, esta página passará a apresentar as informações da clínica.';
    }

    return (
        props.site?.description ||
        'Esta aplicação está em desenvolvimento. A fundação técnica está ativa; os módulos de negócio serão adicionados nas próximas fases.'
    );
});

const heroImageAlt = computed(
    () => props.site?.og_image_alt || 'Imagem de destaque',
);

const primaryColor = computed(() =>
    props.organizationConfigured ? props.site?.primary_color || null : null,
);
const secondaryColor = computed(() =>
    props.organizationConfigured ? props.site?.secondary_color || null : null,
);

const brandStyle = computed(() => ({
    '--brand-primary': primaryColor.value ?? 'var(--primary)',
    '--brand-secondary': secondaryColor.value ?? 'var(--primary)',
}));

const ctaStyle = computed(() =>
    primaryColor.value
        ? { backgroundColor: 'var(--brand-primary)', color: '#fff' }
        : undefined,
);
</script>

<template>
    <Head :title="title" />

    <a
        href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-primary-foreground focus:shadow-lg focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
    >
        Pular para o conteúdo principal
    </a>

    <div
        class="relative flex min-h-screen flex-col overflow-hidden bg-background text-foreground"
        :style="brandStyle"
    >
        <div
            class="pointer-events-none absolute inset-0 -z-10"
            aria-hidden="true"
        >
            <div
                class="absolute -top-24 -left-24 size-96 rounded-full opacity-20 blur-3xl"
                style="background-color: var(--brand-primary)"
            />
            <div
                class="absolute -right-24 -bottom-24 size-96 rounded-full opacity-20 blur-3xl"
                style="background-color: var(--brand-secondary)"
            />
        </div>

        <header class="flex justify-center px-6 pt-10">
            <div
                class="flex size-14 items-center justify-center rounded-2xl"
                style="background-color: var(--brand-primary); color: #fff"
            >
                <AppLogoIcon class="size-7 fill-current" aria-hidden="true" />
                <span class="sr-only">{{ title }}</span>
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
                        {{ title }}
                    </h1>
                    <p
                        class="text-base text-balance text-muted-foreground sm:text-lg"
                    >
                        {{ description }}
                    </p>
                </div>

                <img
                    v-if="organizationConfigured && site?.hero_image_url"
                    :src="site.hero_image_url"
                    :alt="heroImageAlt"
                    fetchpriority="high"
                    class="w-full max-w-md rounded-xl border border-border object-cover shadow-sm"
                />

                <div
                    class="flex w-full max-w-xs flex-col gap-3 sm:flex-row sm:justify-center"
                >
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="w-full"
                    >
                        <Button class="w-full" :style="ctaStyle">
                            Acessar dashboard
                        </Button>
                    </Link>
                    <template v-else>
                        <Link :href="login()" class="w-full">
                            <Button class="w-full" :style="ctaStyle">
                                Entrar
                            </Button>
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
            <p>© {{ new Date().getFullYear() }} {{ title }}</p>
        </footer>
    </div>
</template>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import type { PublicSiteContent } from '@/types/site';

const props = defineProps<{
    site: PublicSiteContent;
}>();

const page = usePage();

// Se o arquivo referenciado no banco não existir mais no storage (arquivo
// removido manualmente, disco trocado etc.), esconde a imagem em vez de
// exibir o ícone de imagem quebrada.
const heroImageFailedToLoad = ref(false);

const brandStyle = {
    '--brand-primary': props.site.primary_color ?? 'var(--primary)',
    '--brand-secondary': props.site.secondary_color ?? 'var(--primary)',
};
</script>

<template>
    <section
        id="hero"
        class="relative scroll-mt-16 overflow-hidden py-16 sm:py-24"
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

        <div class="mx-auto max-w-6xl space-y-12 px-4 sm:px-6">
            <div class="space-y-6 text-center">
                <h1
                    class="text-4xl font-bold tracking-tight text-balance sm:text-5xl"
                >
                    {{ site.title }}
                </h1>
                <p
                    v-if="site.description"
                    class="mx-auto max-w-2xl text-lg text-balance text-muted-foreground"
                >
                    {{ site.description }}
                </p>

                <div
                    class="flex flex-col items-center justify-center gap-3 sm:flex-row"
                >
                    <a
                        v-if="site.cta_text && site.cta_url"
                        :href="site.cta_url"
                    >
                        <Button
                            size="lg"
                            :style="{
                                backgroundColor: 'var(--brand-primary)',
                                color: '#fff',
                            }"
                        >
                            {{ site.cta_text }}
                        </Button>
                    </a>
                    <a
                        v-if="site.cta_secondary_text && site.cta_secondary_url"
                        :href="site.cta_secondary_url"
                    >
                        <Button size="lg" variant="outline">
                            {{ site.cta_secondary_text }}
                        </Button>
                    </a>
                </div>

                <div
                    v-if="!site.cta_text"
                    class="flex flex-col items-center justify-center gap-3 sm:flex-row"
                >
                    <Link v-if="page.props.auth.user" :href="dashboard()">
                        <Button size="lg">Acessar dashboard</Button>
                    </Link>
                    <template v-else>
                        <Link :href="login()">
                            <Button size="lg">Entrar</Button>
                        </Link>
                        <Link :href="register()">
                            <Button size="lg" variant="outline"
                                >Criar conta</Button
                            >
                        </Link>
                    </template>
                </div>
            </div>
        </div>

        <!--
            O banner usa toda a largura da página (fora do container
            max-w-6xl do texto acima), não apenas metade dela — feedback
            direto de homologação. `<picture>`/`<source media>` troca a
            imagem por CSS puro (sem JS): a versão mobile só é baixada e
            exibida em telas pequenas, e some automaticamente se nenhuma
            tiver sido enviada (cai na versão desktop também no mobile).
        -->
        <div
            v-if="site.hero_image_url && !heroImageFailedToLoad"
            class="mt-12 w-full px-4 sm:px-6"
        >
            <picture>
                <source
                    v-if="site.hero_image_mobile_url"
                    media="(max-width: 767px)"
                    :srcset="site.hero_image_mobile_url"
                />
                <img
                    :src="site.hero_image_url"
                    :alt="site.title"
                    fetchpriority="high"
                    class="aspect-4/5 w-full rounded-2xl border border-border object-cover shadow-lg sm:aspect-16/9 lg:aspect-21/9"
                    @error="heroImageFailedToLoad = true"
                />
            </picture>
        </div>
    </section>
</template>

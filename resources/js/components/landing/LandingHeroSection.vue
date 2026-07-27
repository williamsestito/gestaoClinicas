<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import type { PublicBenefit, PublicSiteContent } from '@/types/site';

const props = withDefaults(
    defineProps<{
        site: PublicSiteContent;
        benefits?: PublicBenefit[];
    }>(),
    { benefits: () => [] },
);

const page = usePage();

// Se o arquivo referenciado no banco não existir mais no storage (arquivo
// removido manualmente, disco trocado etc.), esconde a imagem em vez de
// exibir o ícone de imagem quebrada.
const heroImageFailedToLoad = ref(false);

const hasHeroImage = computed(
    () => Boolean(props.site.hero_image_url) && !heroImageFailedToLoad.value,
);

// No desktop, o botão secundário (outline) fica sobre a imagem — o
// variant padrão usa fundo claro, que fica ilegível sobre a foto escura.
const secondaryButtonOverImageClass =
    'md:border-white md:bg-transparent md:text-white md:hover:bg-white/10 md:hover:text-white';

// "Três destaques rápidos" ao lado do título — reaproveita os diferenciais
// já cadastrados (seção "benefits") em vez de um campo novo só para isto.
const quickHighlights = computed(() => props.benefits.slice(0, 3));

// A imagem de fundo do desktop entra como `background-image` (com o
// degradê já embutido) via variável CSS, nunca como elemento posicionado
// `absolute` — evita qualquer chance de a imagem ficar atrás de algo e
// desaparecer. A variável só é *usada* (`md:[background-image:...]`) a
// partir do breakpoint md, então nela não afeta o layout mobile mesmo
// estando sempre definida.
const heroBackgroundVars = computed(() => {
    if (!hasHeroImage.value) {
        return {};
    }

    return {
        '--hero-bg-image': `linear-gradient(to right, rgba(0,0,0,0.75), rgba(0,0,0,0.35) 55%, rgba(0,0,0,0.05)), url("${props.site.hero_image_url}")`,
    };
});
</script>

<template>
    <section id="hero" class="relative scroll-mt-16 overflow-hidden">
        <div
            v-if="!hasHeroImage"
            class="pointer-events-none absolute inset-0 -z-10"
            aria-hidden="true"
        >
            <div
                class="absolute -top-24 -left-24 size-96 rounded-full opacity-20 blur-3xl"
                style="background-color: var(--landing-primary)"
            />
            <div
                class="absolute -right-24 -bottom-24 size-96 rounded-full opacity-20 blur-3xl"
                style="background-color: var(--landing-primary-dark)"
            />
        </div>

        <!--
            No mobile, o banner fica empilhado abaixo do texto (imagem em
            caixa própria). A partir do md, quando há imagem, o banner vira
            plano de fundo em tela cheia (via `background-image`, nunca
            elemento posicionado) com texto sobreposto à esquerda — layout
            pedido a partir de um modelo de referência (hero com overlay
            escuro, texto alinhado à esquerda, dois botões).
        -->
        <div
            class="relative py-16 sm:py-24"
            :class="
                hasHeroImage &&
                'md:flex md:min-h-[520px] md:items-center md:bg-cover md:bg-center md:py-0 md:[background-image:var(--hero-bg-image)] lg:min-h-[620px]'
            "
            :style="heroBackgroundVars"
        >
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div
                    class="space-y-6 text-center"
                    :class="hasHeroImage && 'md:max-w-xl md:text-left md:text-white'"
                >
                    <span
                        v-if="site.schema_type_label"
                        class="landing-eyebrow block"
                        :class="hasHeroImage && 'landing-eyebrow--on-image'"
                    >
                        {{ site.schema_type_label }}
                    </span>

                    <h1
                        class="text-4xl leading-[0.98] font-bold tracking-tight text-balance sm:text-5xl"
                    >
                        {{ site.title }}
                    </h1>
                    <p
                        v-if="site.description"
                        class="mx-auto max-w-2xl text-lg text-balance"
                        :class="
                            hasHeroImage
                                ? 'md:mx-0 md:text-white/90'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ site.description }}
                    </p>

                    <div
                        class="flex flex-col items-center justify-center gap-3 sm:flex-row"
                        :class="hasHeroImage && 'md:justify-start'"
                    >
                        <a
                            v-if="site.cta_text && site.cta_url"
                            :href="site.cta_url"
                        >
                            <Button size="lg" class="rounded-full">
                                {{ site.cta_text }}
                            </Button>
                        </a>
                        <a
                            v-if="
                                site.cta_secondary_text &&
                                site.cta_secondary_url
                            "
                            :href="site.cta_secondary_url"
                        >
                            <Button
                                size="lg"
                                variant="outline"
                                class="rounded-full"
                                :class="hasHeroImage && secondaryButtonOverImageClass"
                            >
                                {{ site.cta_secondary_text }}
                            </Button>
                        </a>
                    </div>

                    <div
                        v-if="!site.cta_text"
                        class="flex flex-col items-center justify-center gap-3 sm:flex-row"
                        :class="hasHeroImage && 'md:justify-start'"
                    >
                        <Link v-if="page.props.auth.user" :href="dashboard()">
                            <Button size="lg" class="rounded-full"
                                >Acessar dashboard</Button
                            >
                        </Link>
                        <template v-else>
                            <Link :href="login()">
                                <Button size="lg" class="rounded-full"
                                    >Entrar</Button
                                >
                            </Link>
                            <Link :href="register()">
                                <Button
                                    size="lg"
                                    variant="outline"
                                    class="rounded-full"
                                    :class="hasHeroImage && secondaryButtonOverImageClass"
                                    >Criar conta</Button
                                >
                            </Link>
                        </template>
                    </div>

                    <ul
                        v-if="quickHighlights.length > 0"
                        class="flex flex-col flex-wrap items-center justify-center gap-x-6 gap-y-2 pt-2 text-sm sm:flex-row"
                        :class="[
                            hasHeroImage
                                ? 'md:justify-start md:text-white/90'
                                : 'text-muted-foreground',
                        ]"
                    >
                        <li
                            v-for="highlight in quickHighlights"
                            :key="highlight.id"
                            class="flex items-center gap-2"
                        >
                            <span
                                class="size-1.5 shrink-0 rounded-full"
                                :style="{
                                    backgroundColor: hasHeroImage
                                        ? 'currentColor'
                                        : 'var(--landing-primary)',
                                }"
                                aria-hidden="true"
                            />
                            {{ highlight.title }}
                        </li>
                    </ul>
                </div>
            </div>

            <picture v-if="hasHeroImage" class="mt-12 block w-full px-4 sm:px-6 md:hidden">
                <source
                    v-if="site.hero_image_mobile_url"
                    media="(max-width: 767px)"
                    :srcset="site.hero_image_mobile_url"
                />
                <img
                    :src="site.hero_image_url ?? undefined"
                    :alt="site.title"
                    fetchpriority="high"
                    class="aspect-4/5 w-full rounded-2xl border border-border object-cover shadow-lg sm:aspect-16/9"
                    @error="heroImageFailedToLoad = true"
                />
            </picture>
        </div>
    </section>
</template>

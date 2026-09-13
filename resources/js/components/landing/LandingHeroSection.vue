<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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

// O banner (quando existe) só aparece a partir do md, como background-image
// — nunca em um <img> próprio, então não há evento de erro para detectar
// falha de carregamento aqui (mesma limitação que o background-image já
// tinha antes desta seção depender de um <img> só para o mobile).
const hasHeroImage = computed(() => Boolean(props.site.hero_image_url));

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

    // Sem texto/botões sobrepostos, o degradê não precisa mais garantir
    // contraste de leitura — só uma leve vinheta para dar profundidade,
    // preservando as cores originais do banner (pedido explícito: o
    // sombreamento forte "apagava" o banner).
    return {
        '--hero-bg-image': `linear-gradient(to right, rgba(0,0,0,0.25), rgba(0,0,0,0.1) 55%, rgba(0,0,0,0)), url("${props.site.hero_image_url}")`,
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
            O banner customizado só aparece a partir do md, como plano de
            fundo em tela cheia (via `background-image`, nunca elemento
            posicionado) com texto sobreposto à esquerda — layout pedido a
            partir de um modelo de referência (hero com overlay escuro,
            texto alinhado à esquerda, dois botões). No mobile ele deixou de
            ser exibido: o mesmo banner já aparece mais abaixo, no corpo da
            landing page, e repeti-lo logo no topo em telas pequenas só
            duplicava conteúdo (achado real, pedido explícito para
            remover) — o hero mobile volta a mostrar título/descrição/CTA
            reais nesse caso, como no hero padrão sem banner.
        -->
        <div
            class="relative py-16 sm:py-24"
            :class="
                hasHeroImage &&
                'md:flex md:min-h-[520px] md:items-center md:[background-image:var(--hero-bg-image)] md:bg-cover md:bg-center md:py-0 lg:min-h-[620px]'
            "
            :style="heroBackgroundVars"
        >
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="space-y-6 text-center">
                    <span
                        v-if="site.schema_type_label"
                        class="landing-eyebrow block"
                        :class="hasHeroImage && 'md:hidden'"
                    >
                        {{ site.schema_type_label }}
                    </span>

                    <!--
                        No desktop com banner, ele já traz sua própria
                        mensagem (logo, título, tagline) desenhada na imagem
                        — sobrepor título/descrição por cima ficava
                        redundante e poluído (achado real, pedido explícito
                        para remover). O <h1> continua no DOM, só
                        visualmente oculto (`sr-only`) a partir do md, para
                        nunca perder a página sem um heading principal.
                    -->
                    <h1
                        class="text-4xl leading-[0.98] font-bold tracking-tight text-balance sm:text-5xl"
                        :class="hasHeroImage && 'md:sr-only'"
                    >
                        {{ site.title }}
                    </h1>
                    <p
                        v-if="site.description"
                        class="mx-auto max-w-2xl text-lg text-balance text-muted-foreground"
                        :class="hasHeroImage && 'md:hidden'"
                    >
                        {{ site.description }}
                    </p>

                    <!--
                        Mesmo raciocínio do título/descrição acima: no
                        desktop, quando o banner já traz seu próprio botão
                        desenhado na imagem, um CTA real sobreposto fica
                        redundante — a navbar já mantém "Agendar
                        horário"/"Acessar o sistema" sempre visíveis como
                        alternativa funcional.
                    -->
                    <div
                        class="flex flex-col items-center justify-center gap-3 sm:flex-row"
                        :class="hasHeroImage && 'md:hidden'"
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
                            >
                                {{ site.cta_secondary_text }}
                            </Button>
                        </a>
                    </div>

                    <div
                        v-if="!site.cta_text"
                        class="flex flex-col items-center justify-center gap-3 sm:flex-row"
                        :class="hasHeroImage && 'md:hidden'"
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
                                    >Criar conta</Button
                                >
                            </Link>
                        </template>
                    </div>

                    <ul
                        v-if="quickHighlights.length > 0"
                        class="flex flex-col flex-wrap items-center justify-center gap-x-6 gap-y-2 pt-2 text-sm text-muted-foreground sm:flex-row"
                        :class="hasHeroImage && 'md:hidden'"
                    >
                        <li
                            v-for="highlight in quickHighlights"
                            :key="highlight.id"
                            class="flex items-center gap-2"
                        >
                            <span
                                class="size-1.5 shrink-0 rounded-full"
                                style="background-color: var(--landing-primary)"
                                aria-hidden="true"
                            />
                            {{ highlight.title }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
</template>

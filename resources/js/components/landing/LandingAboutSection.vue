<script setup lang="ts">
import { computed, ref } from 'vue';
import { benefitIconFor } from '@/lib/benefit-icons';
import type { PublicBenefit, PublicSiteContent } from '@/types/site';

const props = withDefaults(
    defineProps<{
        site: PublicSiteContent;
        benefits?: PublicBenefit[];
    }>(),
    { benefits: () => [] },
);

const heroImageFailedToLoad = ref(false);

// Reaproveita os diferenciais já cadastrados (seção "benefits") como a
// lista de diferenciais desta seção institucional, em vez de um cadastro
// duplicado só para "sobre".
const highlightedBenefits = computed(() => props.benefits.slice(0, 3));
</script>

<template>
    <section
        v-if="site.about_text"
        id="about"
        class="bg-muted/40 scroll-mt-16 py-16"
    >
        <div
            class="mx-auto grid max-w-6xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-2"
        >
            <img
                v-if="site.hero_image_url && !heroImageFailedToLoad"
                :src="site.hero_image_url"
                :alt="site.title"
                loading="lazy"
                class="aspect-4/3 border-border w-full rounded-2xl border object-cover shadow-sm lg:order-first"
                @error="heroImageFailedToLoad = true"
            />

            <div class="space-y-6">
                <p class="landing-eyebrow">Sobre a clínica</p>
                <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Sobre {{ site.title }}
                </h2>
                <p class="text-muted-foreground">{{ site.about_text }}</p>

                <ul
                    v-if="highlightedBenefits.length > 0"
                    class="grid gap-3 sm:grid-cols-2"
                >
                    <li
                        v-for="benefit in highlightedBenefits"
                        :key="benefit.id"
                        class="flex items-start gap-2 text-sm"
                    >
                        <component
                            :is="benefitIconFor(benefit.icon)"
                            class="text-primary mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>{{ benefit.title }}</span>
                    </li>
                </ul>

                <div
                    v-if="site.mission_text || site.vision_text"
                    class="grid gap-4 pt-2 sm:grid-cols-2"
                >
                    <div
                        v-if="site.mission_text"
                        class="border-border bg-card rounded-2xl border p-5"
                    >
                        <p class="landing-eyebrow mb-2">Missão</p>
                        <p class="text-muted-foreground text-sm">
                            {{ site.mission_text }}
                        </p>
                    </div>
                    <div
                        v-if="site.vision_text"
                        class="border-border bg-card rounded-2xl border p-5"
                    >
                        <p class="landing-eyebrow mb-2">Visão</p>
                        <p class="text-muted-foreground text-sm">
                            {{ site.vision_text }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

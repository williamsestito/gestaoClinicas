<script setup lang="ts">
import { ref } from 'vue';
import type { PublicSiteContent } from '@/types/site';

defineProps<{
    site: PublicSiteContent;
}>();

const heroImageFailedToLoad = ref(false);
</script>

<template>
    <section
        v-if="site.about_text"
        id="about"
        class="scroll-mt-16 bg-muted/40 py-16"
    >
        <div
            class="mx-auto grid max-w-6xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-2"
        >
            <div class="space-y-4">
                <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Sobre {{ site.title }}
                </h2>
                <p class="text-muted-foreground">{{ site.about_text }}</p>
            </div>

            <img
                v-if="site.hero_image_url && !heroImageFailedToLoad"
                :src="site.hero_image_url"
                :alt="site.title"
                loading="lazy"
                class="aspect-4/3 w-full rounded-2xl border border-border object-cover shadow-sm"
                @error="heroImageFailedToLoad = true"
            />
        </div>
    </section>
</template>

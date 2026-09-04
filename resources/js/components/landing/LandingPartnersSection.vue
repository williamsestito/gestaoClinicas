<script setup lang="ts">
import type { PublicPartner } from '@/types/site';

defineProps<{
    partners: PublicPartner[];
}>();
</script>

<template>
    <section
        v-if="partners.length > 0"
        id="partners"
        aria-label="Convênios e parceiros"
        class="scroll-mt-16 py-14"
    >
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <p class="landing-eyebrow mb-8 text-center">
                Convênios e parceiros
            </p>

            <div
                class="flex items-center gap-10 overflow-x-auto pb-2 [scrollbar-width:none] sm:flex-wrap sm:justify-center [&::-webkit-scrollbar]:hidden"
            >
                <component
                    :is="partner.url ? 'a' : 'div'"
                    v-for="partner in partners"
                    :key="partner.id"
                    :href="partner.url ?? undefined"
                    :target="partner.url ? '_blank' : undefined"
                    :rel="partner.url ? 'noopener noreferrer' : undefined"
                    class="flex shrink-0 items-center justify-center grayscale transition-all duration-300 hover:grayscale-0"
                >
                    <img
                        v-if="partner.logo_url"
                        :src="partner.logo_url"
                        :alt="partner.name"
                        loading="lazy"
                        class="h-10 w-auto object-contain opacity-70 hover:opacity-100"
                    />
                    <span
                        v-else
                        class="text-muted-foreground text-sm font-medium"
                        >{{ partner.name }}</span
                    >
                </component>
            </div>
        </div>
    </section>
</template>

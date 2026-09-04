<script setup lang="ts">
import type { PublicStatistic } from '@/types/site';

defineProps<{
    statistics: PublicStatistic[];
}>();
</script>

<template>
    <section
        v-if="statistics.length > 0"
        id="indicadores"
        aria-label="Indicadores"
        class="border-t border-b border-border bg-card"
    >
        <div
            class="mx-auto grid max-w-6xl divide-y divide-border px-4 py-10 sm:px-6 sm:py-12"
            :class="[
                statistics.length === 1 && 'grid-cols-1',
                statistics.length === 2 && 'grid-cols-2 divide-y-0 sm:divide-x',
                statistics.length >= 3 &&
                    'grid-cols-2 divide-y-0 sm:grid-cols-4 sm:divide-x',
            ]"
        >
            <div
                v-for="stat in statistics"
                :key="stat.label"
                class="flex flex-col items-center gap-1 px-4 py-4 text-center sm:py-0"
            >
                <span
                    class="text-3xl font-semibold tracking-tight sm:text-4xl"
                    style="font-family: var(--landing-font-title)"
                >
                    {{ stat.value }}
                </span>
                <span class="landing-eyebrow">{{ stat.label }}</span>
            </div>
        </div>
    </section>
</template>

<script setup lang="ts">
import { benefitIconFor } from '@/lib/benefit-icons';
import type { PublicBenefit } from '@/types/site';

defineProps<{
    benefits: PublicBenefit[];
}>();
</script>

<template>
    <section
        v-if="benefits.length > 0"
        id="benefits"
        class="mx-auto max-w-6xl scroll-mt-16 px-4 py-16 sm:px-6"
    >
        <div class="mx-auto mb-10 max-w-2xl text-center">
            <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                Por que escolher a gente
            </h2>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="benefit in benefits"
                :key="benefit.id"
                class="rounded-2xl border border-border bg-card p-6 shadow-sm"
            >
                <div
                    class="mb-4 flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <component
                        :is="benefitIconFor(benefit.icon)"
                        class="size-5"
                    />
                </div>
                <h3 class="font-semibold">{{ benefit.title }}</h3>
                <p
                    v-if="benefit.description"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    {{ benefit.description }}
                </p>
            </div>
        </div>
    </section>
</template>

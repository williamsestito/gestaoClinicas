<script setup lang="ts">
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useLandingScheduling } from '@/composables/useLandingScheduling';
import { formatCurrencyBrl } from '@/lib/masks';
import type { PublicService } from '@/types/site';

const props = defineProps<{
    services: PublicService[];
}>();

const { selectedServiceId } = useLandingScheduling();

const ALL_CATEGORIES = 'Todos';
const activeCategory = ref(ALL_CATEGORIES);

// Categorias vêm só dos serviços realmente cadastrados — nunca uma lista
// fixa (ex.: "Estética", "Odontologia"), já que cada clínica define as
// suas livremente no campo `category` de cada serviço.
const categories = computed(() => {
    const found = new Set(
        props.services
            .map((service) => service.category)
            .filter((c): c is string => Boolean(c)),
    );

    return found.size > 0 ? [ALL_CATEGORIES, ...found] : [];
});

const filteredServices = computed(() => {
    if (activeCategory.value === ALL_CATEGORIES) {
        return props.services;
    }

    return props.services.filter(
        (service) => service.category === activeCategory.value,
    );
});

function formatPrice(cents: number | null): string | null {
    return cents === null ? null : formatCurrencyBrl(cents);
}

function selectService(id: number) {
    selectedServiceId.value = id;
}
</script>

<template>
    <section
        v-if="services.length > 0"
        id="services"
        class="mx-auto max-w-6xl scroll-mt-16 px-4 py-16 sm:px-6"
    >
        <div class="mx-auto mb-10 max-w-2xl text-center">
            <p class="landing-eyebrow mb-2">O que oferecemos</p>
            <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                Serviços e tratamentos
            </h2>
        </div>

        <div
            v-if="categories.length > 1"
            class="mb-8 flex [scrollbar-width:none] justify-start gap-2 overflow-x-auto pb-1 sm:justify-center [&::-webkit-scrollbar]:hidden"
            role="tablist"
            aria-label="Filtrar serviços por categoria"
        >
            <button
                v-for="category in categories"
                :key="category"
                type="button"
                role="tab"
                :aria-selected="activeCategory === category"
                class="shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium transition-colors"
                :class="
                    activeCategory === category
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border text-muted-foreground hover:text-foreground'
                "
                @click="activeCategory = category"
            >
                {{ category }}
            </button>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="service in filteredServices"
                :key="service.id"
                class="flex flex-col overflow-hidden rounded-(--landing-radius-md) py-0"
            >
                <img
                    v-if="service.image_url"
                    :src="service.image_url"
                    :alt="service.name"
                    loading="lazy"
                    class="h-44 w-full object-cover"
                />
                <CardContent class="flex flex-1 flex-col gap-2 p-6">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-semibold">{{ service.name }}</h3>
                        <Badge v-if="service.is_featured" variant="secondary"
                            >Destaque</Badge
                        >
                    </div>
                    <p
                        v-if="service.short_description"
                        class="text-sm text-muted-foreground"
                    >
                        {{ service.short_description }}
                    </p>

                    <div
                        class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span v-if="service.duration_minutes"
                            >{{ service.duration_minutes }} min</span
                        >
                        <span v-if="formatPrice(service.starting_price_cents)"
                            >A partir de
                            {{
                                formatPrice(service.starting_price_cents)
                            }}</span
                        >
                    </div>

                    <a
                        href="#scheduling"
                        class="mt-auto pt-3"
                        @click="selectService(service.id)"
                    >
                        <Button class="w-full rounded-full" variant="outline">
                            {{ service.cta_text || 'Agendar' }}
                        </Button>
                    </a>
                </CardContent>
            </Card>
        </div>
    </section>
</template>

<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useLandingScheduling } from '@/composables/useLandingScheduling';
import type { PublicService } from '@/types/site';

defineProps<{
    services: PublicService[];
}>();

const { selectedServiceId } = useLandingScheduling();

function formatPrice(cents: number | null): string | null {
    if (cents === null) {
        return null;
    }

    return (cents / 100).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
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
            <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                Serviços e tratamentos
            </h2>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="service in services"
                :key="service.id"
                class="flex flex-col overflow-hidden py-0"
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
                        <Button class="w-full" variant="outline">
                            {{ service.cta_text || 'Agendar' }}
                        </Button>
                    </a>
                </CardContent>
            </Card>
        </div>
    </section>
</template>

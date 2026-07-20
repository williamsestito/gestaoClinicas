<script setup lang="ts">
import { Mail, MapPin, MessageCircle, Phone } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { PublicContact } from '@/types/site';

const props = defineProps<{
    contact: PublicContact;
}>();

const DAY_LABELS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

const sortedHours = computed(() =>
    [...props.contact.opening_hours].sort(
        (a, b) => a.day_of_week - b.day_of_week,
    ),
);

const mapsSearchUrl = computed(() => {
    if (props.contact.map_url) {
        return props.contact.map_url;
    }

    if (!props.contact.address) {
        return null;
    }

    const { street, number, city, state } = props.contact.address;
    const query = encodeURIComponent(`${street}, ${number} - ${city}/${state}`);

    return `https://www.google.com/maps/search/?api=1&query=${query}`;
});

const whatsappUrl = computed(() => {
    if (!props.contact.whatsapp) {
        return null;
    }

    return `https://wa.me/55${props.contact.whatsapp.replace(/\D/g, '')}`;
});
</script>

<template>
    <section id="contact" class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="mx-auto mb-10 max-w-2xl text-center">
            <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                Contato e localização
            </h2>
        </div>

        <div class="grid gap-8 lg:grid-cols-2">
            <div
                class="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-sm"
            >
                <div v-if="contact.address" class="flex gap-3">
                    <MapPin class="mt-0.5 size-5 shrink-0 text-primary" />
                    <div>
                        <p class="font-medium">
                            {{ contact.address.street }},
                            {{ contact.address.number }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ contact.address.city }}/{{
                                contact.address.state
                            }}
                        </p>
                    </div>
                </div>

                <div v-if="contact.phone" class="flex items-center gap-3">
                    <Phone class="size-5 shrink-0 text-primary" />
                    <a :href="`tel:${contact.phone}`" class="hover:underline">
                        {{ contact.phone }}
                    </a>
                </div>

                <div v-if="contact.email" class="flex items-center gap-3">
                    <Mail class="size-5 shrink-0 text-primary" />
                    <a
                        :href="`mailto:${contact.email}`"
                        class="hover:underline"
                    >
                        {{ contact.email }}
                    </a>
                </div>

                <div v-if="sortedHours.length > 0" class="pt-2">
                    <p class="mb-2 text-sm font-medium">
                        Horário de atendimento
                    </p>
                    <ul class="space-y-1 text-sm text-muted-foreground">
                        <li
                            v-for="hour in sortedHours"
                            :key="`${hour.day_of_week}-${hour.opens_at}`"
                        >
                            {{ DAY_LABELS[hour.day_of_week] }}:
                            {{ hour.opens_at }} às {{ hour.closes_at }}
                        </li>
                    </ul>
                </div>

                <div class="flex flex-wrap gap-3 pt-2">
                    <a
                        v-if="mapsSearchUrl"
                        :href="mapsSearchUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <Button variant="outline">
                            <MapPin class="size-4" />
                            Ver no mapa
                        </Button>
                    </a>
                    <a
                        v-if="whatsappUrl"
                        :href="whatsappUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <Button variant="outline">
                            <MessageCircle class="size-4" />
                            WhatsApp
                        </Button>
                    </a>
                </div>
            </div>

            <div
                v-if="mapsSearchUrl"
                class="overflow-hidden rounded-2xl border border-border"
            >
                <a
                    :href="mapsSearchUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex h-full min-h-64 flex-col items-center justify-center gap-2 bg-muted text-muted-foreground transition-colors hover:bg-muted/70"
                >
                    <MapPin class="size-8" />
                    <span class="text-sm font-medium"
                        >Abrir localização no mapa</span
                    >
                </a>
            </div>
        </div>
    </section>
</template>

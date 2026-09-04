<script setup lang="ts">
import { Star, User } from '@lucide/vue';
import type { PublicTestimonial } from '@/types/site';

defineProps<{
    testimonials: PublicTestimonial[];
}>();
</script>

<template>
    <section
        v-if="testimonials.length > 0"
        id="testimonials"
        class="mx-auto max-w-6xl scroll-mt-16 px-4 py-16 sm:px-6"
    >
        <div class="mx-auto mb-10 max-w-2xl text-center">
            <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                Quem já passou por aqui
            </h2>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <figure
                v-for="testimonial in testimonials"
                :key="testimonial.id"
                class="border-border bg-card flex flex-col gap-3 rounded-2xl border p-6 shadow-sm"
            >
                <div
                    v-if="testimonial.rating"
                    class="flex gap-0.5 text-amber-500"
                    role="img"
                    :aria-label="`Avaliação: ${testimonial.rating} de 5`"
                >
                    <Star
                        v-for="n in 5"
                        :key="n"
                        class="size-4"
                        :class="
                            n <= testimonial.rating
                                ? 'fill-current'
                                : 'text-muted-foreground/40'
                        "
                    />
                </div>

                <blockquote class="text-muted-foreground flex-1 text-sm">
                    "{{ testimonial.content }}"
                </blockquote>

                <figcaption class="flex items-center gap-3">
                    <img
                        v-if="testimonial.author_photo_url"
                        :src="testimonial.author_photo_url"
                        :alt="testimonial.author_name"
                        loading="lazy"
                        class="size-10 rounded-full object-cover"
                    />
                    <div
                        v-else
                        class="bg-muted text-muted-foreground flex size-10 items-center justify-center rounded-full"
                    >
                        <User class="size-4" />
                    </div>
                    <div>
                        <p class="text-sm font-medium">
                            {{ testimonial.author_name }}
                        </p>
                        <p
                            v-if="testimonial.related_service_name"
                            class="text-muted-foreground text-xs"
                        >
                            {{ testimonial.related_service_name }}
                        </p>
                    </div>
                </figcaption>
            </figure>
        </div>
    </section>
</template>

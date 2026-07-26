<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PublicGalleryItem } from '@/types/site';

// Quantidade exibida no carrossel da home — a galeria completa fica na
// página "Ver todas" (ver App\Http\Controllers\PublicGalleryController),
// que já pagina no backend. Aqui é só uma prévia.
const PREVIEW_LIMIT = 12;

const props = defineProps<{
    items: PublicGalleryItem[];
}>();

const previewItems = computed(() => props.items.slice(0, PREVIEW_LIMIT));
const hasMore = computed(() => props.items.length > PREVIEW_LIMIT);

const trackRef = ref<HTMLElement | null>(null);

function scrollByAmount(direction: 1 | -1) {
    const track = trackRef.value;

    if (!track) {
        return;
    }

    track.scrollBy({ left: direction * track.clientWidth * 0.8, behavior: 'smooth' });
}

const openIndex = ref<number | null>(null);
const isOpen = computed(() => openIndex.value !== null);
const current = computed(() =>
    openIndex.value !== null ? previewItems.value[openIndex.value] : null,
);

function open(index: number) {
    openIndex.value = index;
}

function close() {
    openIndex.value = null;
}

function next() {
    if (openIndex.value === null) {
        return;
    }

    openIndex.value = (openIndex.value + 1) % previewItems.value.length;
}

function previous() {
    if (openIndex.value === null) {
        return;
    }

    openIndex.value =
        (openIndex.value - 1 + previewItems.value.length) % previewItems.value.length;
}
</script>

<template>
    <section
        v-if="items.length > 0"
        id="gallery"
        class="scroll-mt-16 bg-muted/40 py-16"
    >
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div
                class="mb-10 flex flex-col items-center justify-between gap-4 sm:flex-row"
            >
                <div class="mx-auto max-w-2xl text-center sm:mx-0 sm:text-left">
                    <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Galeria
                    </h2>
                </div>
                <Link
                    v-if="hasMore"
                    href="/galeria"
                    class="shrink-0"
                >
                    <Button variant="outline">Ver todas</Button>
                </Link>
            </div>

            <div class="relative">
                <div
                    ref="trackRef"
                    class="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    role="region"
                    aria-label="Carrossel de imagens da galeria"
                    tabindex="0"
                    @keydown.left="scrollByAmount(-1)"
                    @keydown.right="scrollByAmount(1)"
                >
                    <button
                        v-for="(item, index) in previewItems"
                        :key="item.id"
                        type="button"
                        class="group w-40 shrink-0 snap-start overflow-hidden rounded-xl border border-border focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:w-56"
                        :aria-label="`Ver imagem: ${item.caption || item.alt_text || 'imagem da galeria'}`"
                        @click="open(index)"
                    >
                        <img
                            :src="item.image_url"
                            :alt="item.alt_text ?? item.caption ?? ''"
                            loading="lazy"
                            class="aspect-square w-full object-cover transition-transform duration-300 group-hover:scale-105"
                        />
                        <p
                            v-if="item.caption"
                            class="truncate bg-card px-2 py-1.5 text-left text-xs text-muted-foreground"
                        >
                            {{ item.caption }}
                        </p>
                    </button>
                </div>

                <button
                    v-if="previewItems.length > 1"
                    type="button"
                    class="absolute top-1/2 left-0 hidden -translate-x-1/2 -translate-y-1/2 rounded-full border border-border bg-background p-2 shadow-sm hover:bg-muted sm:block"
                    aria-label="Deslizar galeria para a esquerda"
                    @click="scrollByAmount(-1)"
                >
                    <ChevronLeft class="size-4" />
                </button>
                <button
                    v-if="previewItems.length > 1"
                    type="button"
                    class="absolute top-1/2 right-0 hidden translate-x-1/2 -translate-y-1/2 rounded-full border border-border bg-background p-2 shadow-sm hover:bg-muted sm:block"
                    aria-label="Deslizar galeria para a direita"
                    @click="scrollByAmount(1)"
                >
                    <ChevronRight class="size-4" />
                </button>
            </div>
        </div>

        <Dialog :open="isOpen" @update:open="(open) => !open && close()">
            <DialogContent
                class="max-w-3xl gap-0 border-0 bg-transparent p-0 shadow-none"
            >
                <DialogTitle class="sr-only">
                    {{
                        current?.caption ||
                        current?.alt_text ||
                        'Imagem da galeria'
                    }}
                </DialogTitle>
                <div class="relative">
                    <img
                        v-if="current"
                        :src="current.image_url"
                        :alt="current.alt_text ?? current.caption ?? ''"
                        class="max-h-[80vh] w-full rounded-lg object-contain"
                    />
                    <p
                        v-if="current?.caption"
                        class="mt-2 text-center text-sm text-white"
                    >
                        {{ current.caption }}
                    </p>

                    <DialogClose
                        class="absolute top-2 right-2 rounded-full bg-black/60 p-2 text-white hover:bg-black/80"
                        aria-label="Fechar"
                    >
                        <X class="size-4" />
                    </DialogClose>

                    <button
                        v-if="previewItems.length > 1"
                        type="button"
                        class="absolute top-1/2 left-2 -translate-y-1/2 rounded-full bg-black/60 p-2 text-white hover:bg-black/80"
                        aria-label="Imagem anterior"
                        @click="previous"
                    >
                        <ChevronLeft class="size-5" />
                    </button>
                    <button
                        v-if="previewItems.length > 1"
                        type="button"
                        class="absolute top-1/2 right-2 -translate-y-1/2 rounded-full bg-black/60 p-2 text-white hover:bg-black/80"
                        aria-label="Próxima imagem"
                        @click="next"
                    >
                        <ChevronRight class="size-5" />
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    </section>
</template>

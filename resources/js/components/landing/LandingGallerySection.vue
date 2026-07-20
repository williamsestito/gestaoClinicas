<script setup lang="ts">
import { ChevronLeft, ChevronRight, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PublicGalleryItem } from '@/types/site';

const props = defineProps<{
    items: PublicGalleryItem[];
}>();

const openIndex = ref<number | null>(null);
const isOpen = computed(() => openIndex.value !== null);
const current = computed(() =>
    openIndex.value !== null ? props.items[openIndex.value] : null,
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

    openIndex.value = (openIndex.value + 1) % props.items.length;
}

function previous() {
    if (openIndex.value === null) {
        return;
    }

    openIndex.value =
        (openIndex.value - 1 + props.items.length) % props.items.length;
}
</script>

<template>
    <section v-if="items.length > 0" id="gallery" class="bg-muted/40 py-16">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="mx-auto mb-10 max-w-2xl text-center">
                <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Galeria
                </h2>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <button
                    v-for="(item, index) in items"
                    :key="item.id"
                    type="button"
                    class="group overflow-hidden rounded-xl border border-border focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :aria-label="`Ver imagem: ${item.caption || item.alt_text || 'imagem da galeria'}`"
                    @click="open(index)"
                >
                    <img
                        :src="item.image_url"
                        :alt="item.alt_text ?? item.caption ?? ''"
                        loading="lazy"
                        class="aspect-square w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
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
                        v-if="items.length > 1"
                        type="button"
                        class="absolute top-1/2 left-2 -translate-y-1/2 rounded-full bg-black/60 p-2 text-white hover:bg-black/80"
                        aria-label="Imagem anterior"
                        @click="previous"
                    >
                        <ChevronLeft class="size-5" />
                    </button>
                    <button
                        v-if="items.length > 1"
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

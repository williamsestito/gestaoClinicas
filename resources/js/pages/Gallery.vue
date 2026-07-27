<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ChevronLeft, ChevronRight, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';

type GalleryItem = {
    id: number;
    image_url: string;
    caption: string | null;
    alt_text: string | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    siteTitle: string;
    logoUrl: string | null;
    items: Paginated<GalleryItem> | null;
}>();

const openIndex = ref<number | null>(null);
const isOpen = computed(() => openIndex.value !== null);
const items = computed(() => props.items?.data ?? []);
const current = computed(() =>
    openIndex.value !== null ? items.value[openIndex.value] : null,
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

    openIndex.value = (openIndex.value + 1) % items.value.length;
}

function previous() {
    if (openIndex.value === null) {
        return;
    }

    openIndex.value = (openIndex.value - 1 + items.value.length) % items.value.length;
}

function goToPage(url: string | null) {
    if (url) {
        router.visit(url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="`Galeria — ${siteTitle}`" />

    <div class="landing-theme flex min-h-screen flex-col bg-background text-foreground">
        <header class="border-b">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6"
            >
                <Link href="/" class="flex items-center gap-2">
                    <img
                        v-if="logoUrl"
                        :src="logoUrl"
                        :alt="siteTitle"
                        class="h-8 w-auto object-contain"
                    />
                    <AppLogoIcon v-else class="size-8 fill-current" />
                    <span class="font-semibold">{{ siteTitle }}</span>
                </Link>
                <Link href="/">
                    <Button variant="outline">
                        <ArrowLeft class="size-4" />
                        Voltar para o início
                    </Button>
                </Link>
            </div>
        </header>

        <main class="flex-1 py-12">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <h1 class="mb-8 text-2xl font-semibold tracking-tight sm:text-3xl">
                    Galeria
                </h1>

                <p
                    v-if="items.length === 0"
                    class="text-muted-foreground"
                >
                    Nenhuma imagem publicada no momento.
                </p>

                <template v-else>
                    <div
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
                    >
                        <button
                            v-for="(item, index) in items"
                            :key="item.id"
                            type="button"
                            class="group overflow-hidden rounded-xl border border-border text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
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
                                class="truncate px-2 py-1.5 text-xs text-muted-foreground"
                            >
                                {{ item.caption }}
                            </p>
                        </button>
                    </div>

                    <nav
                        v-if="props.items && props.items.last_page > 1"
                        class="mt-8 flex flex-wrap justify-center gap-1"
                        aria-label="Paginação da galeria"
                    >
                        <button
                            v-for="link in props.items.links"
                            :key="link.label"
                            type="button"
                            :disabled="!link.url"
                            class="rounded-md px-3 py-1 text-sm"
                            :class="[
                                link.active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-muted',
                                !link.url && 'pointer-events-none opacity-50',
                            ]"
                            v-html="link.label"
                            @click="goToPage(link.url)"
                        />
                    </nav>
                </template>
            </div>
        </main>

        <footer class="border-t py-6 text-center text-sm text-muted-foreground">
            <Link href="/" class="hover:underline">{{ siteTitle }}</Link>
        </footer>
    </div>

    <Dialog :open="isOpen" @update:open="(open) => !open && close()">
        <DialogContent
            class="max-w-3xl gap-0 border-0 bg-transparent p-0 shadow-none"
        >
            <DialogTitle class="sr-only">
                {{ current?.caption || current?.alt_text || 'Imagem da galeria' }}
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
</template>

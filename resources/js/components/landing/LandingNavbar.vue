<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { LANDING_NAV_LABELS } from '@/lib/landing-nav';
import { dashboard, login } from '@/routes';
import type { LandingSectionType } from '@/types/site';

const props = defineProps<{
    title: string;
    logoUrl: string | null;
    activeTypes: LandingSectionType[];
}>();

const navLinks = computed(() =>
    props.activeTypes
        .filter(
            (type): type is keyof typeof LANDING_NAV_LABELS =>
                type in LANDING_NAV_LABELS,
        )
        .map((type) => ({ type, label: LANDING_NAV_LABELS[type]! })),
);

const showScheduling = computed(() => props.activeTypes.includes('scheduling'));

const page = usePage();
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));

const mobileOpen = ref(false);
</script>

<template>
    <header
        class="sticky top-0 z-40 border-b border-border/60 bg-background/90 backdrop-blur supports-[backdrop-filter]:bg-background/70"
    >
        <div
            class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6"
        >
            <a href="#hero" class="flex items-center gap-2 font-semibold">
                <img
                    v-if="logoUrl"
                    :src="logoUrl"
                    :alt="title"
                    class="size-9 rounded-lg object-cover"
                />
                <AppLogoIcon
                    v-else
                    class="size-9 rounded-lg fill-current text-primary"
                    aria-hidden="true"
                />
                <span class="truncate">{{ title }}</span>
            </a>

            <nav
                class="hidden items-center gap-6 text-sm font-medium text-muted-foreground lg:flex"
                aria-label="Seções da página"
            >
                <a
                    v-for="link in navLinks"
                    :key="link.type"
                    :href="`#${link.type}`"
                    class="transition-colors hover:text-foreground"
                >
                    {{ link.label }}
                </a>
            </nav>

            <div class="hidden items-center gap-2 lg:flex">
                <a v-if="showScheduling" href="#scheduling">
                    <Button size="sm">Agendar horário</Button>
                </a>
                <Link :href="isAuthenticated ? dashboard() : login()">
                    <Button size="sm" variant="outline">
                        Acessar o sistema
                    </Button>
                </Link>
            </div>

            <Button
                variant="ghost"
                size="icon"
                class="lg:hidden"
                aria-label="Abrir menu"
                @click="mobileOpen = true"
            >
                <Menu class="size-5" />
            </Button>
        </div>

        <Sheet v-model:open="mobileOpen">
            <SheetContent side="right" class="w-full max-w-xs">
                <SheetHeader>
                    <SheetTitle>{{ title }}</SheetTitle>
                </SheetHeader>
                <nav
                    class="flex flex-col gap-1 px-4"
                    aria-label="Seções da página"
                >
                    <SheetClose
                        as-child
                        v-for="link in navLinks"
                        :key="link.type"
                    >
                        <a
                            :href="`#${link.type}`"
                            class="rounded-md px-3 py-2 text-sm font-medium hover:bg-muted"
                        >
                            {{ link.label }}
                        </a>
                    </SheetClose>
                </nav>
                <div class="mt-4 flex flex-col gap-2 px-4 pb-4">
                    <SheetClose v-if="showScheduling" as-child>
                        <a href="#scheduling">
                            <Button class="w-full">Agendar horário</Button>
                        </a>
                    </SheetClose>
                    <SheetClose as-child>
                        <Link
                            :href="isAuthenticated ? dashboard() : login()"
                            class="w-full"
                        >
                            <Button variant="outline" class="w-full">
                                Acessar o sistema
                            </Button>
                        </Link>
                    </SheetClose>
                </div>
            </SheetContent>
        </Sheet>
    </header>
</template>

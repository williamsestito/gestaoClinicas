<script setup lang="ts">
import { Check, Monitor, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/types';

const { appearance, updateAppearance } = useAppearance();

const options: { value: Appearance; icon: typeof Sun; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Claro' },
    { value: 'dark', icon: Moon, label: 'Escuro' },
    { value: 'system', icon: Monitor, label: 'Sistema' },
];

const currentIcon = computed(
    () =>
        options.find((option) => option.value === appearance.value)?.icon ??
        Monitor,
);
</script>

<template>
    <DropdownMenu>
        <Tooltip>
            <TooltipTrigger as-child>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Alternar tema"
                    >
                        <component :is="currentIcon" class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
            </TooltipTrigger>
            <TooltipContent>Tema</TooltipContent>
        </Tooltip>

        <DropdownMenuContent align="end">
            <DropdownMenuItem
                v-for="option in options"
                :key="option.value"
                class="justify-between"
                @select="updateAppearance(option.value)"
            >
                <span class="flex items-center gap-2">
                    <component :is="option.icon" class="size-4" />
                    {{ option.label }}
                </span>
                <Check v-if="appearance === option.value" class="size-4" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

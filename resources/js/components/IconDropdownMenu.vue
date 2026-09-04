<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

withDefaults(
    defineProps<{
        icon: LucideIcon;
        label: string;
        emptyTitle: string;
        emptyDescription?: string;
        count?: number;
    }>(),
    {
        emptyDescription: undefined,
        count: 0,
    },
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
                        class="relative"
                        :aria-label="label"
                    >
                        <component :is="icon" class="size-4" />
                        <span
                            v-if="count > 0"
                            class="bg-primary absolute right-1 top-1 flex size-2 rounded-full"
                            aria-hidden="true"
                        />
                    </Button>
                </DropdownMenuTrigger>
            </TooltipTrigger>
            <TooltipContent>{{ label }}</TooltipContent>
        </Tooltip>

        <DropdownMenuContent align="end" class="w-80">
            <DropdownMenuLabel>{{ label }}</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <EmptyState :title="emptyTitle" :description="emptyDescription" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>

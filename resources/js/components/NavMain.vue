<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { reactive } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavGroup } from '@/types';

defineProps<{
    groups: NavGroup[];
}>();

const { isCurrentUrl } = useCurrentUrl();
const { state: sidebarState } = useSidebar();

const manuallyOpenedGroups = reactive<Record<string, boolean>>({});

function groupContainsCurrentUrl(group: NavGroup): boolean {
    return group.items.some(
        (item) => !item.disabled && isCurrentUrl(item.href),
    );
}

function isGroupOpen(group: NavGroup): boolean {
    if (sidebarState.value === 'collapsed') {
        return true;
    }

    return manuallyOpenedGroups[group.title] ?? groupContainsCurrentUrl(group);
}

function setGroupOpen(group: NavGroup, value: boolean) {
    manuallyOpenedGroups[group.title] = value;
}
</script>

<template>
    <Collapsible
        v-for="group in groups"
        :key="group.title"
        as-child
        class="group/collapsible"
        :open="isGroupOpen(group)"
        @update:open="(value) => setGroupOpen(group, value)"
    >
        <SidebarGroup class="px-2 py-0">
            <SidebarGroupLabel as-child>
                <CollapsibleTrigger
                    class="flex w-full items-center gap-2 group-data-[collapsible=icon]:pointer-events-none"
                >
                    <span class="truncate">{{ group.title }}</span>
                    <ChevronRight
                        class="ml-auto size-4 shrink-0 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                    />
                </CollapsibleTrigger>
            </SidebarGroupLabel>
            <CollapsibleContent>
                <SidebarMenu>
                    <SidebarMenuItem
                        v-for="item in group.items"
                        :key="item.title"
                    >
                        <SidebarMenuButton
                            v-if="!item.disabled"
                            as-child
                            :is-active="isCurrentUrl(item.href)"
                            :tooltip="item.title"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                        <SidebarMenuButton
                            v-else
                            disabled
                            aria-disabled="true"
                            class="cursor-not-allowed opacity-50"
                            :tooltip="`${item.title} — em breve`"
                        >
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                            <span
                                class="ml-auto rounded bg-sidebar-accent px-1.5 py-0.5 text-[10px] font-medium text-sidebar-accent-foreground group-data-[collapsible=icon]:hidden"
                            >
                                Em breve
                            </span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </CollapsibleContent>
        </SidebarGroup>
    </Collapsible>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Building2, FileText, LayoutGrid, MapPin } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as indexLegalEntities } from '@/routes/settings/legal-entities';
import { edit as editOrganization } from '@/routes/settings/organization';
import { index as indexUnits } from '@/routes/settings/units';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Visão geral',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Dados da clínica',
        href: editOrganization(),
        icon: Building2,
    },
    {
        title: 'Dados legais e fiscais',
        href: indexLegalEntities(),
        icon: FileText,
    },
    {
        title: 'Unidades',
        href: indexUnits(),
        icon: MapPin,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

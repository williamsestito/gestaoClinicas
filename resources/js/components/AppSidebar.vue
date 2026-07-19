<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Banknote,
    Building2,
    CalendarDays,
    FileText,
    LayoutGrid,
    MapPin,
    ShieldCheck,
    Stethoscope,
    Users,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import TenantSwitcher from '@/components/TenantSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as indexLegalEntities } from '@/routes/settings/legal-entities';
import { edit as editOrganization } from '@/routes/settings/organization';
import { index as indexUnits } from '@/routes/settings/units';
import type { NavGroup } from '@/types';

const navGroups: NavGroup[] = [
    {
        title: 'Visão Geral',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
        ],
    },
    {
        title: 'Operação',
        items: [
            { title: 'Agenda', href: '#', icon: CalendarDays, disabled: true },
            { title: 'Pacientes', href: '#', icon: Users, disabled: true },
            {
                title: 'Profissionais',
                href: '#',
                icon: Stethoscope,
                disabled: true,
            },
            { title: 'Serviços', href: '#', icon: ShieldCheck, disabled: true },
        ],
    },
    {
        title: 'Clínica',
        items: [
            {
                title: 'Dados da clínica',
                href: editOrganization(),
                icon: Building2,
            },
            {
                title: 'Unidades',
                href: indexUnits(),
                icon: MapPin,
            },
            {
                title: 'Entidades legais',
                href: indexLegalEntities(),
                icon: FileText,
            },
        ],
    },
    {
        title: 'Gestão',
        items: [
            { title: 'Usuários', href: '#', icon: Users, disabled: true },
            {
                title: 'Perfis e permissões',
                href: '#',
                icon: ShieldCheck,
                disabled: true,
            },
        ],
    },
    {
        title: 'Financeiro',
        items: [
            { title: 'Receitas', href: '#', icon: Banknote, disabled: true },
            { title: 'Despesas', href: '#', icon: Banknote, disabled: true },
            {
                title: 'Formas de pagamento',
                href: '#',
                icon: Banknote,
                disabled: true,
            },
        ],
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
            <div class="px-2 pb-1 group-data-[collapsible=icon]:hidden">
                <TenantSwitcher />
            </div>
        </SidebarHeader>

        <SidebarSeparator class="group-data-[collapsible=icon]:hidden" />

        <SidebarContent>
            <NavMain :groups="navGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

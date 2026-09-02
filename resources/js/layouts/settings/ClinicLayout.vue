<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { index as indexAudit } from '@/routes/settings/audit';
import { index as indexLegalEntities } from '@/routes/settings/legal-entities';
import { edit as editModules } from '@/routes/settings/modules';
import { edit as editOrganization } from '@/routes/settings/organization';
import { index as indexRoles } from '@/routes/settings/roles';
import { edit as editSeo } from '@/routes/settings/seo';
import { edit as editSite } from '@/routes/settings/site';
import { index as indexUnits } from '@/routes/settings/units';
import { index as indexUsers } from '@/routes/settings/users';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Dados da clínica',
        href: editOrganization(),
    },
    {
        title: 'Entidades legais',
        href: indexLegalEntities(),
    },
    {
        title: 'Unidades',
        href: indexUnits(),
    },
    {
        title: 'Módulos',
        href: editModules(),
    },
    {
        title: 'Usuários',
        href: indexUsers(),
    },
    {
        title: 'Perfis e permissões',
        href: indexRoles(),
    },
    {
        title: 'Site da clínica',
        href: editSite(),
    },
    {
        title: 'SEO e marketing',
        href: editSeo(),
    },
    {
        title: 'Auditoria',
        href: indexAudit(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Configurações da clínica"
            description="Gerencie os dados, entidades legais e unidades da sua clínica"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Configurações da clínica"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <!--
                Sem max-width aqui: esta área é compartilhada por páginas de
                formulário simples (Dados da clínica, SEO) e por páginas de
                tabela (Usuários, Papéis, Auditoria, Unidades) — um teto
                fixo deixava as tabelas espremidas no desktop. Cada página
                aplica seu próprio max-width quando fizer sentido (ver
                Organization.vue, settings/site/Index.vue).
            -->
            <div class="min-w-0 flex-1">
                <section class="space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>

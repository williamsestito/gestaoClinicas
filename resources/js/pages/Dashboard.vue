<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as indexLegalEntities } from '@/routes/settings/legal-entities';
import { edit as editOrganization } from '@/routes/settings/organization';
import { edit as editSeo } from '@/routes/settings/seo';
import { edit as editSite } from '@/routes/settings/site';
import { index as indexUnits } from '@/routes/settings/units';
import { index as indexUsers } from '@/routes/settings/users';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Visão geral',
                href: dashboard(),
            },
        ],
    },
});

defineProps<{
    organizationName: string | null;
    unitsCount: number;
    usersCount: number;
    activeUsersCount: number;
    inactiveUsersCount: number;
    legalEntitiesCount: number;
    primaryLegalEntity: {
        legal_name: string;
        trade_name: string | null;
    } | null;
    domainConfigured: boolean;
    seoConfigured: boolean;
    recentActivity: {
        id: string;
        actor: string;
        action: string;
        entity: string;
        created_at: string | null;
    }[];
    pendingSetupItems: string[];
}>();

const page = usePage();
const tenant = computed(() => page.props.tenant);
</script>

<template>
    <Head title="Visão geral" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <Card v-if="tenant?.organization">
            <CardHeader>
                <CardTitle>{{ organizationName }}</CardTitle>
                <CardDescription>
                    Unidade ativa:
                    {{ tenant.unit ? tenant.unit.name : 'nenhuma selecionada' }}
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">
                        Status da clínica
                    </p>
                    <Badge
                        :variant="
                            tenant.organization.status === 'active'
                                ? 'default'
                                : 'destructive'
                        "
                    >
                        {{
                            tenant.organization.status === 'active'
                                ? 'Ativa'
                                : 'Inativa'
                        }}
                    </Badge>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">Unidades</p>
                    <p class="font-medium">{{ unitsCount }}</p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">
                        Entidades legais
                    </p>
                    <p class="font-medium">{{ legalEntitiesCount }}</p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">
                        Entidade legal principal
                    </p>
                    <p class="font-medium">
                        {{
                            primaryLegalEntity?.trade_name ??
                            primaryLegalEntity?.legal_name ??
                            'Não cadastrada'
                        }}
                    </p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">Usuários</p>
                    <p class="font-medium">
                        {{ usersCount }} ({{ activeUsersCount }} ativos,
                        {{ inactiveUsersCount }} inativos)
                    </p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">Domínio do site</p>
                    <Badge
                        :variant="domainConfigured ? 'default' : 'secondary'"
                    >
                        {{
                            domainConfigured ? 'Configurado' : 'Não configurado'
                        }}
                    </Badge>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">SEO</p>
                    <Badge :variant="seoConfigured ? 'default' : 'secondary'">
                        {{ seoConfigured ? 'Configurado' : 'Pendente' }}
                    </Badge>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">Seu papel</p>
                    <p class="font-medium">
                        {{ tenant.isOwner ? 'Proprietário(a)' : 'Membro' }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card v-if="pendingSetupItems.length > 0">
            <CardHeader>
                <CardTitle>Pendências de configuração</CardTitle>
                <CardDescription>
                    Itens que ainda precisam de atenção.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ul class="list-inside list-disc space-y-1 text-sm">
                    <li v-for="item in pendingSetupItems" :key="item">
                        {{ item }}
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Atalhos</CardTitle>
                <CardDescription
                    >Acesse rapidamente as configurações da
                    clínica.</CardDescription
                >
            </CardHeader>
            <CardContent class="flex flex-wrap gap-3 text-sm">
                <Link
                    :href="editOrganization()"
                    class="text-primary underline-offset-4 hover:underline"
                    >Dados da clínica</Link
                >
                <Link
                    :href="indexLegalEntities()"
                    class="text-primary underline-offset-4 hover:underline"
                    >Entidades legais</Link
                >
                <Link
                    :href="indexUnits()"
                    class="text-primary underline-offset-4 hover:underline"
                    >Unidades</Link
                >
                <Link
                    :href="indexUsers()"
                    class="text-primary underline-offset-4 hover:underline"
                    >Usuários</Link
                >
                <Link
                    :href="editSite()"
                    class="text-primary underline-offset-4 hover:underline"
                    >Configurar página pública</Link
                >
                <Link
                    :href="editSeo()"
                    class="text-primary underline-offset-4 hover:underline"
                    >Configurar SEO</Link
                >
                <a
                    href="/"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-primary underline-offset-4 hover:underline"
                    >Visualizar site</a
                >
            </CardContent>
        </Card>

        <Card v-if="recentActivity.length > 0">
            <CardHeader>
                <CardTitle>Últimas atividades</CardTitle>
            </CardHeader>
            <CardContent>
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="activity in recentActivity"
                        :key="activity.id"
                        class="text-muted-foreground"
                    >
                        <span class="font-medium text-foreground">{{
                            activity.actor
                        }}</span>
                        {{ activity.action.toLowerCase() }}
                        {{ activity.entity }}
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>

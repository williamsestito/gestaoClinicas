<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import OrganizationAgendaCard from '@/components/dashboard/OrganizationAgendaCard.vue';
import type { OrgAgendaData } from '@/components/dashboard/OrganizationAgendaCard.vue';
import ProfessionalDashboard from '@/components/dashboard/ProfessionalDashboard.vue';
import type { ProfessionalDashboardData } from '@/components/dashboard/ProfessionalDashboard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { index as indexAppointmentRequests } from '@/routes/settings/site/appointment-requests';
import { index as indexUnits } from '@/routes/settings/units';
import { index as indexUsers } from '@/routes/settings/users';

type PendingAppointmentRequestGroup = {
    professional_id: string | null;
    professional_name: string;
    count: number;
    requests: {
        id: string;
        name: string;
        phone: string;
        service_name: string | null;
        created_at: string | null;
    }[];
};

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
    professionalDashboard: ProfessionalDashboardData | null;
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
    pendingAppointmentRequestsByProfessional:
        PendingAppointmentRequestGroup[] | null;
    orgAgenda: OrgAgendaData | null;
}>();

const page = usePage();
const tenant = computed(() => page.props.tenant);
</script>

<template>
    <Head title="Visão geral" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <ProfessionalDashboard
            v-if="professionalDashboard"
            :data="professionalDashboard"
        />

        <template v-else>
            <Card
                v-if="
                    pendingAppointmentRequestsByProfessional &&
                    pendingAppointmentRequestsByProfessional.length > 0
                "
                class="border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30"
            >
                <CardHeader>
                    <CardTitle
                        class="flex items-center gap-2 text-amber-900 dark:text-amber-200"
                    >
                        <AlertTriangle
                            class="size-5 text-amber-600 dark:text-amber-400"
                        />
                        Pré-agendamentos aguardando confirmação
                    </CardTitle>
                    <CardDescription class="text-amber-800 dark:text-amber-300"
                        >Qualquer pessoa com acesso pode confirmar — não é
                        exclusivo do profissional. Separado por
                        profissional.</CardDescription
                    >
                </CardHeader>
                <CardContent class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="group in pendingAppointmentRequestsByProfessional"
                        :key="group.professional_id ?? 'none'"
                        class="flex flex-col gap-2 rounded-lg border border-amber-200 bg-white/60 p-3 dark:border-amber-900 dark:bg-black/10"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p
                                class="font-medium text-amber-900 dark:text-amber-200"
                            >
                                {{ group.professional_name }}
                            </p>
                            <Badge variant="outline">{{ group.count }}</Badge>
                        </div>
                        <ul
                            class="grid gap-1 text-sm text-amber-800 dark:text-amber-300"
                        >
                            <li
                                v-for="request in group.requests"
                                :key="request.id"
                            >
                                {{ request.name }} — {{ request.phone }}
                                <span v-if="request.service_name"
                                    >({{ request.service_name }})</span
                                >
                            </li>
                        </ul>
                        <Button
                            as-child
                            size="sm"
                            class="mt-1 w-fit bg-amber-600 hover:bg-amber-700"
                        >
                            <Link
                                :href="
                                    indexAppointmentRequests({
                                        query: {
                                            status: 'pending',
                                            professional_id:
                                                group.professional_id ??
                                                undefined,
                                        },
                                    }).url
                                "
                            >
                                Ver e confirmar
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <OrganizationAgendaCard v-if="orgAgenda" :data="orgAgenda" />

            <Card v-if="tenant?.organization">
                <CardHeader>
                    <CardTitle>{{ organizationName }}</CardTitle>
                    <CardDescription>
                        Unidade ativa:
                        {{
                            tenant.unit
                                ? tenant.unit.name
                                : 'nenhuma selecionada'
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="space-y-1">
                        <p class="text-muted-foreground text-sm">
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
                        <p class="text-muted-foreground text-sm">Unidades</p>
                        <p class="font-medium">{{ unitsCount }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-muted-foreground text-sm">
                            Entidades legais
                        </p>
                        <p class="font-medium">{{ legalEntitiesCount }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-muted-foreground text-sm">
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
                        <p class="text-muted-foreground text-sm">Usuários</p>
                        <p class="font-medium">
                            {{ usersCount }} ({{ activeUsersCount }} ativos,
                            {{ inactiveUsersCount }} inativos)
                        </p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-muted-foreground text-sm">
                            Domínio do site
                        </p>
                        <Badge
                            :variant="
                                domainConfigured ? 'default' : 'secondary'
                            "
                        >
                            {{
                                domainConfigured
                                    ? 'Configurado'
                                    : 'Não configurado'
                            }}
                        </Badge>
                    </div>
                    <div class="space-y-1">
                        <p class="text-muted-foreground text-sm">SEO</p>
                        <Badge
                            :variant="seoConfigured ? 'default' : 'secondary'"
                        >
                            {{ seoConfigured ? 'Configurado' : 'Pendente' }}
                        </Badge>
                    </div>
                    <div class="space-y-1">
                        <p class="text-muted-foreground text-sm">Seu papel</p>
                        <p class="font-medium">
                            {{
                                tenant.isOwner
                                    ? 'Proprietário(a)'
                                    : (tenant.membership?.role_name ?? 'Membro')
                            }}
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
                            <span class="text-foreground font-medium">{{
                                activity.actor
                            }}</span>
                            {{ activity.action.toLowerCase() }}
                            {{ activity.entity }}
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </template>
    </div>
</template>

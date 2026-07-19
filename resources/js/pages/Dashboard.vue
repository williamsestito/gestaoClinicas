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
import { index as indexUnits } from '@/routes/settings/units';

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
    appEnvironment: string;
    unitsCount: number;
    primaryLegalEntity: {
        legal_name: string;
        trade_name: string | null;
    } | null;
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
                <CardTitle>{{ tenant.organization.name }}</CardTitle>
                <CardDescription>
                    Unidade ativa:
                    {{ tenant.unit ? tenant.unit.name : 'nenhuma selecionada' }}
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
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
                    <p class="text-sm text-muted-foreground">Seu papel</p>
                    <p class="font-medium">
                        {{ tenant.isOwner ? 'Proprietário(a)' : 'Membro' }}
                    </p>
                </div>
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
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Fundação técnica ativa</CardTitle>
                <CardDescription>
                    A infraestrutura base da plataforma (autenticação, banco de
                    dados, fila, cache e painel administrativo) está configurada
                    e em funcionamento.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">Ambiente atual</p>
                    <Badge variant="secondary">{{ appEnvironment }}</Badge>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrencyBrl, formatDateTimeBr } from '@/lib/masks';
import { dashboard } from '@/routes';
import { create, show } from '@/routes/settings/sales';

type SaleRow = {
    id: string;
    patient_name: string;
    unit_name: string;
    status: string;
    status_label: string;
    total_cents: number;
    created_at: string;
};

type PaginatedSales = {
    data: SaleRow[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

defineProps<{
    sales: PaginatedSales;
    filters: { patient_id?: string; unit_id?: string; status?: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Produtos e vendas' },
            { title: 'Vendas' },
        ],
    },
});

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'confirmed') {
        return 'default';
    }

    if (status === 'cancelled') {
        return 'destructive';
    }

    if (status === 'pending_approval') {
        return 'outline';
    }

    return 'secondary';
}
</script>

<template>
    <Head title="Vendas" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Vendas"
            description="Vendas de produtos, serviços e pacotes"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="create().url">Nova venda</Link>
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="sales.data.length === 0"
            title="Nenhuma venda registrada"
            description="Crie a primeira venda a partir da ficha de um paciente."
        />

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3">Paciente</th>
                        <th class="px-4 py-3">Unidade</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Data</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="sale in sales.data" :key="sale.id">
                        <td class="px-4 py-3 font-medium">
                            {{ sale.patient_name }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ sale.unit_name }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge :variant="statusVariant(sale.status)">
                                {{ sale.status_label }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ formatCurrencyBrl(sale.total_cents) }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ formatDateTimeBr(sale.created_at) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="show(sale.id).url"
                                class="text-sm font-medium text-primary hover:underline"
                            >
                                Ver
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="sales.links.length > 3"
            aria-label="Paginação das vendas"
            class="flex flex-wrap gap-1"
        >
            <template v-for="(link, linkIndex) in sales.links" :key="linkIndex">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :aria-current="link.active ? 'page' : undefined"
                    :class="[
                        'rounded-md px-3 py-1 text-sm',
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted',
                    ]"
                    preserve-scroll
                >
                    <span v-html="link.label" />
                </Link>
                <span
                    v-else
                    class="pointer-events-none rounded-md px-3 py-1 text-sm text-muted-foreground opacity-50"
                    aria-disabled="true"
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>

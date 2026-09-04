<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatCurrencyBrl } from '@/lib/masks';
import { dashboard } from '@/routes';
import {
    activate,
    create,
    deactivate,
    destroy,
    edit,
    restore as restoreRoute,
} from '@/routes/settings/products';

type ProductRow = {
    id: string;
    name: string;
    code: string;
    unit_of_measure: string;
    price_cents: number | null;
    status: 'active' | 'inactive';
    deleted_at: string | null;
};

defineProps<{
    products: ProductRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Produtos e vendas' },
            { title: 'Produtos' },
        ],
    },
});

const processingId = ref<string | null>(null);

function toggleActivate(product: ProductRow) {
    processingId.value = product.id;
    const routeFn = product.status === 'active' ? deactivate : activate;

    router.patch(
        routeFn(product.id).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function remove(product: ProductRow) {
    if (!confirm(`Excluir o produto "${product.name}"?`)) {
        return;
    }

    processingId.value = product.id;
    router.delete(destroy(product.id).url, {
        preserveScroll: true,
        onFinish: () => (processingId.value = null),
    });
}

function restore(product: ProductRow) {
    processingId.value = product.id;
    router.post(
        restoreRoute(product.id).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}
</script>

<template>
    <Head title="Produtos" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Produtos"
            description="Produtos vendáveis da clínica"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="create().url">Novo produto</Link>
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="products.length === 0"
            title="Nenhum produto cadastrado"
            description="Cadastre produtos para vendê-los junto com serviços e pacotes."
        />

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3">Nome</th>
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3">Unidade</th>
                        <th class="px-4 py-3">Preço</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="product in products" :key="product.id">
                        <td class="px-4 py-3 font-medium">
                            {{ product.name }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ product.code }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ product.unit_of_measure }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{
                                product.price_cents !== null
                                    ? formatCurrencyBrl(product.price_cents)
                                    : '—'
                            }}
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge
                                :status="product.status"
                                :deleted-at="product.deleted_at"
                            />
                        </td>
                        <td
                            class="flex flex-wrap justify-end gap-2 px-4 py-3 text-right"
                        >
                            <template v-if="product.deleted_at">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="processingId === product.id"
                                    @click="restore(product)"
                                >
                                    Restaurar
                                </Button>
                            </template>
                            <template v-else>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="edit(product.id).url">
                                        Editar
                                    </Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="processingId === product.id"
                                    @click="toggleActivate(product)"
                                >
                                    {{
                                        product.status === 'active'
                                            ? 'Inativar'
                                            : 'Ativar'
                                    }}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="processingId === product.id"
                                    @click="remove(product)"
                                >
                                    Excluir
                                </Button>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

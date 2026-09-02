<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import type { EditableProduct } from '@/components/products/ProductForm.vue';
import ProductForm from '@/components/products/ProductForm.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/products';

const props = defineProps<{
    product: EditableProduct;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Produtos e vendas' },
            { title: 'Produtos', href: index() },
            { title: 'Editar produto' },
        ],
    },
});

function cancel() {
    router.get(index().url);
}
</script>

<template>
    <Head title="Editar produto" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Editar produto"
            :description="`Atualize os dados de ${props.product.name}`"
        />

        <ProductForm mode="edit" :product="props.product" @cancel="cancel" />
    </div>
</template>

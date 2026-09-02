<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ProductForm from '@/components/products/ProductForm.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/products';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Produtos e vendas' },
            { title: 'Produtos', href: index() },
            { title: 'Novo produto' },
        ],
    },
});

function cancel() {
    router.get(index().url);
}
</script>

<template>
    <Head title="Novo produto" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Novo produto"
            description="Cadastre um novo produto vendável da clínica"
        />

        <ProductForm mode="create" @cancel="cancel" />
    </div>
</template>

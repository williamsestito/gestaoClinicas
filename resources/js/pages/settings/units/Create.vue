<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import UnitForm from '@/components/units/UnitForm.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/units';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Configurações da clínica' },
            { title: 'Unidades', href: index() },
            { title: 'Nova unidade' },
        ],
    },
});

defineProps<{
    states: string[];
}>();

function cancel() {
    router.get(index().url);
}
</script>

<template>
    <Head title="Nova unidade" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Nova unidade"
            description="Cadastre uma nova unidade da organização"
        />

        <UnitForm mode="create" :states="states" @cancel="cancel" />
    </div>
</template>

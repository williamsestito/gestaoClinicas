<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import UnitForm from '@/components/units/UnitForm.vue';
import type { EditableUnit } from '@/components/units/UnitForm.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/units';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Configurações da clínica' },
            { title: 'Unidades', href: index() },
            { title: 'Editar unidade' },
        ],
    },
});

defineProps<{
    unit: EditableUnit;
}>();

function cancel() {
    router.get(index().url);
}
</script>

<template>
    <Head :title="`Editar ${unit.name}`" />

    <div class="flex flex-col space-y-6">
        <PageHeader title="Editar unidade" :description="unit.name" />

        <UnitForm mode="edit" :unit="unit" @cancel="cancel" />
    </div>
</template>

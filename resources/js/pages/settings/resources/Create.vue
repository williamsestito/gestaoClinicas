<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ResourceForm from '@/components/resources/ResourceForm.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/resources';

defineProps<{
    units: { id: string; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Agenda' },
            { title: 'Recursos', href: index() },
            { title: 'Novo recurso' },
        ],
    },
});

function cancel() {
    router.get(index().url);
}
</script>

<template>
    <Head title="Novo recurso" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Novo recurso"
            description="Cadastre uma nova sala ou equipamento"
        />

        <ResourceForm mode="create" :units="units" @cancel="cancel" />
    </div>
</template>

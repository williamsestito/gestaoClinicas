<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import type { EditableResource } from '@/components/resources/ResourceForm.vue';
import ResourceForm from '@/components/resources/ResourceForm.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/resources';

const props = defineProps<{
    resource: EditableResource;
    units: { id: string; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Agenda' },
            { title: 'Recursos', href: index() },
            { title: 'Editar recurso' },
        ],
    },
});

function cancel() {
    router.get(index().url);
}
</script>

<template>
    <Head title="Editar recurso" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Editar recurso"
            :description="`Atualize os dados de ${props.resource.name}`"
        />

        <ResourceForm
            mode="edit"
            :resource="props.resource"
            :units="units"
            @cancel="cancel"
        />
    </div>
</template>

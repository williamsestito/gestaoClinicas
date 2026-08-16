<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import type {
    EditableService,
    ServiceOption,
    UnitOption,
} from '@/components/services/ServiceForm.vue';
import ServiceForm from '@/components/services/ServiceForm.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/services';

const props = defineProps<{
    service: EditableService;
    specialties: ServiceOption[];
    units: UnitOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e serviços' },
            { title: 'Serviços e procedimentos', href: index() },
            { title: 'Editar serviço' },
        ],
    },
});

function cancel() {
    router.get(index().url);
}
</script>

<template>
    <Head title="Editar serviço" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Editar serviço"
            :description="`Atualize os dados de ${props.service.name}`"
        />

        <ServiceForm
            mode="edit"
            :service="props.service"
            :specialties="specialties"
            :units="units"
            @cancel="cancel"
        />
    </div>
</template>

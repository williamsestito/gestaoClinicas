<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import type {
    ServiceOption,
    UnitOption,
} from '@/components/services/ServiceForm.vue';
import ServiceForm from '@/components/services/ServiceForm.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/services';

defineProps<{
    specialties: ServiceOption[];
    units: UnitOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e serviços' },
            { title: 'Serviços e procedimentos', href: index() },
            { title: 'Novo serviço' },
        ],
    },
});

function cancel() {
    router.get(index().url);
}
</script>

<template>
    <Head title="Novo serviço" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Novo serviço"
            description="Cadastre um novo serviço ou procedimento da clínica"
        />

        <ServiceForm
            mode="create"
            :specialties="specialties"
            :units="units"
            @cancel="cancel"
        />
    </div>
</template>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PatientCoreForm from '@/components/patients/PatientCoreForm.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/patients';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Pacientes', href: index() },
            { title: 'Novo paciente' },
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
    <Head title="Novo paciente" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Novo paciente"
            description="Cadastre um novo paciente da clínica."
        />

        <PatientCoreForm mode="create" :states="states" @cancel="cancel" />
    </div>
</template>

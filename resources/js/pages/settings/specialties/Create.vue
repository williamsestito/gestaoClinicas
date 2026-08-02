<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import SpecialtyForm from '@/components/specialties/SpecialtyForm.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/specialties';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e serviços' },
            { title: 'Especialidades', href: index() },
            { title: 'Nova especialidade' },
        ],
    },
});

function cancel() {
    router.get(index().url);
}
</script>

<template>
    <Head title="Nova especialidade" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Nova especialidade"
            description="Cadastre uma nova especialidade da clínica"
        />

        <SpecialtyForm mode="create" @cancel="cancel" />
    </div>
</template>

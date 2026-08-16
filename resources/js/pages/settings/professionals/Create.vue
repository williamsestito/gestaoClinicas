<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ProfessionalForm from '@/components/professionals/ProfessionalForm.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/professionals';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e serviços' },
            { title: 'Profissionais', href: index() },
            { title: 'Novo profissional' },
        ],
    },
});

function cancel() {
    router.get(index().url);
}
</script>

<template>
    <Head title="Novo profissional" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Novo profissional"
            description="Cadastre um novo profissional da clínica"
        />

        <ProfessionalForm mode="create" @cancel="cancel" />
    </div>
</template>

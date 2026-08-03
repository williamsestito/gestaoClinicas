<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Minha agenda' },
        ],
    },
});
</script>

<template>
    <Head title="Minha agenda" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Minha agenda"
            description="Configure sua jornada, vigência e ausências."
        />

        <EmptyState
            title="Você não possui um cadastro profissional vinculado."
            description="Peça a um administrador da clínica para vincular seu usuário a um cadastro de profissional para configurar sua agenda."
        />
    </div>
</template>

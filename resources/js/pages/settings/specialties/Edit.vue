<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import type { EditableSpecialty } from '@/components/specialties/SpecialtyForm.vue';
import SpecialtyForm from '@/components/specialties/SpecialtyForm.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/specialties';

const props = defineProps<{
    specialty: EditableSpecialty;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e serviços' },
            { title: 'Especialidades', href: index() },
            { title: 'Editar especialidade' },
        ],
    },
});

function cancel() {
    router.get(index().url);
}
</script>

<template>
    <Head title="Editar especialidade" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Editar especialidade"
            :description="`Atualize os dados de ${props.specialty.name}`"
        />

        <SpecialtyForm
            mode="edit"
            :specialty="props.specialty"
            @cancel="cancel"
        />
    </div>
</template>

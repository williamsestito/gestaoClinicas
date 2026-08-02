<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ProfessionalTabs from '@/components/professionals/ProfessionalTabs.vue';
import type { UnitOption } from '@/components/professionals/TimeBlockForm.vue';
import TimeBlocksSection from '@/components/professionals/TimeBlocksSection.vue';
import type { TimeBlockRow } from '@/components/professionals/TimeBlocksSection.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/professionals';

const props = defineProps<{
    professional: {
        id: string;
        display_name: string;
        status: 'active' | 'inactive';
    };
    timeBlocks: TimeBlockRow[];
    eligibleUnits: UnitOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e serviços' },
            { title: 'Profissionais', href: index() },
            { title: 'Ausências e bloqueios' },
        ],
    },
});
</script>

<template>
    <Head title="Ausências e bloqueios do profissional" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Ausências e bloqueios"
            description="Cadastre férias, folgas, ausências e bloqueios que reduzem a disponibilidade regular deste profissional."
        />

        <ProfessionalTabs
            :professional="props.professional"
            active="time-blocks"
        />

        <TimeBlocksSection
            :professional-id="props.professional.id"
            :time-blocks="props.timeBlocks"
            :eligible-units="props.eligibleUnits"
        />
    </div>
</template>

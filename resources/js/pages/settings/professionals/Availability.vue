<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import ProfessionalTabs from '@/components/professionals/ProfessionalTabs.vue';
import WeeklyScheduleSection from '@/components/professionals/WeeklyScheduleSection.vue';
import type { AvailabilityUnit } from '@/components/professionals/WeeklyScheduleSection.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/professionals';

const props = defineProps<{
    professional: {
        id: string;
        display_name: string;
        status: 'active' | 'inactive';
    };
    units: AvailabilityUnit[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e serviços' },
            { title: 'Profissionais', href: index() },
            { title: 'Jornada e disponibilidade' },
        ],
    },
});
</script>

<template>
    <Head title="Jornada e disponibilidade do profissional" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Jornada e disponibilidade"
            description="Configure os horários regulares deste profissional em cada unidade de atuação."
        />

        <ProfessionalTabs
            :professional="props.professional"
            active="availability"
        />

        <EmptyState
            v-if="props.units.length === 0"
            title="Este profissional ainda não possui unidade de atuação ativa."
            description="Vincule o profissional a uma unidade para configurar a jornada."
        />

        <div v-else class="grid gap-4">
            <WeeklyScheduleSection
                v-for="unit in props.units"
                :key="unit.professional_unit_id"
                :professional-id="props.professional.id"
                :unit="unit"
            />
        </div>
    </div>
</template>

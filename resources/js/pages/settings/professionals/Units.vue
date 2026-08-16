<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ProfessionalTabs from '@/components/professionals/ProfessionalTabs.vue';
import UnitLinksSection from '@/components/professionals/UnitLinksSection.vue';
import type {
    UnitLink,
    UnitOption,
} from '@/components/professionals/UnitLinksSection.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/professionals';

const props = defineProps<{
    professional: {
        id: string;
        display_name: string;
        status: 'active' | 'inactive';
    };
    unitLinks: UnitLink[];
    eligibleUnits: UnitOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e serviços' },
            { title: 'Profissionais', href: index() },
            { title: 'Unidades de atuação' },
        ],
    },
});
</script>

<template>
    <Head title="Unidades de atuação do profissional" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Unidades de atuação"
            description="Gerencie as unidades em que este profissional atua e sua vigência."
        />

        <ProfessionalTabs :professional="props.professional" active="units" />

        <div>
            <h3 class="mb-3 text-sm font-medium">Unidades</h3>
            <UnitLinksSection
                :professional-id="props.professional.id"
                :links="props.unitLinks"
                :eligible-units="props.eligibleUnits"
            />
        </div>
    </div>
</template>

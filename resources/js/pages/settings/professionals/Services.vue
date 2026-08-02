<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ProfessionalTabs from '@/components/professionals/ProfessionalTabs.vue';
import type {
    ProfessionalUnitOption,
    ServiceOption,
} from '@/components/professionals/ServiceAssignmentForm.vue';
import ServiceLinksSection from '@/components/professionals/ServiceLinksSection.vue';
import type { ServiceLink } from '@/components/professionals/ServiceLinksSection.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/professionals';

const props = defineProps<{
    professional: {
        id: string;
        display_name: string;
        status: 'active' | 'inactive';
    };
    serviceLinks: ServiceLink[];
    eligibleServices: ServiceOption[];
    professionalUnits: ProfessionalUnitOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e serviços' },
            { title: 'Profissionais', href: index() },
            { title: 'Serviços executados' },
        ],
    },
});
</script>

<template>
    <Head title="Serviços executados pelo profissional" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Serviços executados"
            description="Gerencie os serviços que este profissional executa, com valores e unidades compatíveis."
        />

        <ProfessionalTabs
            :professional="props.professional"
            active="services"
        />

        <div>
            <h3 class="mb-3 text-sm font-medium">Serviços</h3>
            <ServiceLinksSection
                :professional-id="props.professional.id"
                :links="props.serviceLinks"
                :eligible-services="props.eligibleServices"
                :professional-units="props.professionalUnits"
            />
        </div>
    </div>
</template>

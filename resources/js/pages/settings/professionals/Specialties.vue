<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ProfessionalTabs from '@/components/professionals/ProfessionalTabs.vue';
import RegistrationsSection from '@/components/professionals/RegistrationsSection.vue';
import type { RegistrationRow } from '@/components/professionals/RegistrationsSection.vue';
import SpecialtyLinksSection from '@/components/professionals/SpecialtyLinksSection.vue';
import type {
    SpecialtyLink,
    SpecialtyOption,
} from '@/components/professionals/SpecialtyLinksSection.vue';
import { Separator } from '@/components/ui/separator';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/professionals';

const props = defineProps<{
    professional: {
        id: string;
        display_name: string;
        status: 'active' | 'inactive';
    };
    specialtyLinks: SpecialtyLink[];
    eligibleSpecialties: SpecialtyOption[];
    registrations: RegistrationRow[];
    canViewSensitiveRegistrations: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e serviços' },
            { title: 'Profissionais', href: index() },
            { title: 'Especialidades e registros' },
        ],
    },
});
</script>

<template>
    <Head title="Especialidades e registros do profissional" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Especialidades e registros"
            description="Gerencie as especialidades e os registros profissionais deste colaborador."
        />

        <ProfessionalTabs
            :professional="props.professional"
            active="specialties"
        />

        <div class="grid gap-6">
            <div>
                <h3 class="mb-3 text-sm font-medium">Especialidades</h3>
                <SpecialtyLinksSection
                    :professional-id="props.professional.id"
                    :links="props.specialtyLinks"
                    :eligible-specialties="props.eligibleSpecialties"
                />
            </div>

            <Separator />

            <div>
                <h3 class="mb-3 text-sm font-medium">
                    Registros profissionais
                </h3>
                <RegistrationsSection
                    :professional-id="props.professional.id"
                    :registrations="props.registrations"
                    :can-view-sensitive="props.canViewSensitiveRegistrations"
                />
            </div>
        </div>
    </div>
</template>

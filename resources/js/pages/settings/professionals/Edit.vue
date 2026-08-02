<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ProfessionalForm from '@/components/professionals/ProfessionalForm.vue';
import type {
    EditableProfessional,
    EligibleUser,
} from '@/components/professionals/ProfessionalForm.vue';
import ProfessionalOperationalSummary from '@/components/professionals/ProfessionalOperationalSummary.vue';
import type { OperationalSummary } from '@/components/professionals/ProfessionalOperationalSummary.vue';
import ProfessionalPhotoUpload from '@/components/professionals/ProfessionalPhotoUpload.vue';
import ProfessionalTabs from '@/components/professionals/ProfessionalTabs.vue';
import ProfessionalUserLink from '@/components/professionals/ProfessionalUserLink.vue';
import { Separator } from '@/components/ui/separator';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/professionals';

const props = defineProps<{
    professional: EditableProfessional & {
        photo_url: string | null;
        linked_user: { id: number; name: string } | null;
        status: 'active' | 'inactive';
    };
    eligibleUsers: EligibleUser[];
    operationalSummary: OperationalSummary;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e serviços' },
            { title: 'Profissionais', href: index() },
            { title: 'Editar profissional' },
        ],
    },
});

function cancel() {
    router.get(index().url);
}
</script>

<template>
    <Head title="Editar profissional" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Editar profissional"
            :description="`Atualize os dados de ${props.professional.display_name}`"
        />

        <ProfessionalTabs :professional="props.professional" active="general" />

        <ProfessionalOperationalSummary :summary="props.operationalSummary" />

        <div class="grid gap-6">
            <div>
                <h3 class="mb-3 text-sm font-medium">Dados profissionais</h3>
                <ProfessionalForm
                    mode="edit"
                    :professional="props.professional"
                    @cancel="cancel"
                />
            </div>

            <Separator />

            <div>
                <h3 class="mb-3 text-sm font-medium">Foto</h3>
                <ProfessionalPhotoUpload
                    :professional-id="props.professional.id"
                    :photo-url="props.professional.photo_url"
                />
            </div>

            <Separator />

            <div>
                <h3 class="mb-1 text-sm font-medium">Usuário vinculado</h3>
                <p class="text-muted-foreground mb-3 text-sm">
                    Vínculo opcional — não concede acesso nem permissões por si
                    só.
                </p>
                <ProfessionalUserLink
                    :professional-id="props.professional.id"
                    :linked-user="props.professional.linked_user"
                    :eligible-users="eligibleUsers"
                />
            </div>
        </div>
    </div>
</template>

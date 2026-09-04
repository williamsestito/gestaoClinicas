<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { formatDateTimeBr } from '@/lib/masks';
import { dashboard } from '@/routes';
import { show as showMedicalRecord } from '@/routes/settings/medical-records';
import { index as myPatientsIndex } from '@/routes/settings/my-patients';

type MedicalRecordRow = {
    id: string;
    appointment_id: string;
    status: 'draft' | 'finalized';
    status_label: string;
    professional_name: string | null;
    appointment_starts_at: string | null;
    finalized_at: string | null;
};

type PaginatedRecords = {
    data: MedicalRecordRow[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

const props = defineProps<{
    patient: { id: string; name: string };
    records: PaginatedRecords;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Meus pacientes', href: myPatientsIndex() },
            { title: 'Prontuários' },
        ],
    },
});
</script>

<template>
    <Head :title="`Prontuários — ${props.patient.name}`" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Histórico de prontuários"
            :description="props.patient.name"
        />

        <EmptyState
            v-if="records.data.length === 0"
            title="Nenhum prontuário registrado"
            description="Os prontuários dos atendimentos deste paciente aparecerão aqui assim que forem criados."
        />

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-muted-foreground text-left text-xs uppercase"
                >
                    <tr>
                        <th class="px-4 py-3">Atendimento</th>
                        <th class="px-4 py-3">Profissional</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Finalizado em</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="record in records.data" :key="record.id">
                        <td class="px-4 py-3">
                            {{
                                record.appointment_starts_at
                                    ? formatDateTimeBr(
                                          record.appointment_starts_at,
                                      )
                                    : '—'
                            }}
                        </td>
                        <td class="text-muted-foreground px-4 py-3">
                            {{ record.professional_name ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    record.status === 'draft'
                                        ? 'secondary'
                                        : 'default'
                                "
                            >
                                {{ record.status_label }}
                            </Badge>
                        </td>
                        <td class="text-muted-foreground px-4 py-3">
                            {{
                                record.finalized_at
                                    ? formatDateTimeBr(record.finalized_at)
                                    : '—'
                            }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="
                                    showMedicalRecord(record.appointment_id).url
                                "
                                class="text-primary text-sm font-medium hover:underline"
                            >
                                Ver prontuário
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="records.links.length > 3"
            aria-label="Paginação dos prontuários"
            class="flex flex-wrap gap-1"
        >
            <template
                v-for="(link, linkIndex) in records.links"
                :key="linkIndex"
            >
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :aria-current="link.active ? 'page' : undefined"
                    :class="[
                        'rounded-md px-3 py-1 text-sm',
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted',
                    ]"
                    preserve-scroll
                >
                    <span v-html="link.label" />
                </Link>
                <span
                    v-else
                    class="text-muted-foreground pointer-events-none rounded-md px-3 py-1 text-sm opacity-50"
                    aria-disabled="true"
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>

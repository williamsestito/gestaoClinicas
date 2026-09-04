<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type MedicalRecordAddendum = {
    id: string;
    body: string;
    created_at: string;
};

type MedicalRecordFile = {
    id: string;
    category_label: string;
    original_filename: string;
};

type MedicalRecordRow = {
    id: string;
    appointment_starts_at: string | null;
    professional_name: string | null;
    anamnesis: string | null;
    evaluation: string | null;
    treatment_plan: string | null;
    evolution_notes: string | null;
    prescriptions: string | null;
    referrals: string | null;
    has_return_right: boolean;
    return_window_days: number | null;
    finalized_at: string | null;
    addenda: MedicalRecordAddendum[];
    files: MedicalRecordFile[];
};

defineProps<{
    patient: { id: string; name: string };
    records: MedicalRecordRow[];
}>();

defineOptions({
    layout: {
        title: 'Meus prontuários',
    },
});

const sections: { key: keyof MedicalRecordRow; label: string }[] = [
    { key: 'anamnesis', label: 'Anamnese' },
    { key: 'evaluation', label: 'Avaliação' },
    { key: 'treatment_plan', label: 'Plano de tratamento' },
    { key: 'evolution_notes', label: 'Evolução e orientações' },
    { key: 'prescriptions', label: 'Prescrições' },
    { key: 'referrals', label: 'Encaminhamentos' },
];

function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Meus prontuários" />

    <div class="flex flex-col gap-6">
        <h1 class="text-xl font-medium">Prontuários de {{ patient.name }}</h1>

        <EmptyState
            v-if="records.length === 0"
            title="Nenhum prontuário disponível"
            description="Os registros dos seus atendimentos aparecerão aqui assim que forem liberados pela clínica."
        />

        <Card v-for="record in records" :key="record.id">
            <CardHeader>
                <CardTitle class="flex flex-wrap items-center gap-2 text-base">
                    <span v-if="record.appointment_starts_at">
                        {{ formatDateTime(record.appointment_starts_at) }}
                    </span>
                    <span
                        v-if="record.professional_name"
                        class="text-muted-foreground text-sm font-normal"
                    >
                        Com {{ record.professional_name }}
                    </span>
                </CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <template v-for="section in sections" :key="section.key">
                    <div v-if="record[section.key]">
                        <p class="text-muted-foreground text-sm font-medium">
                            {{ section.label }}
                        </p>
                        <p class="whitespace-pre-line text-sm">
                            {{ record[section.key] }}
                        </p>
                    </div>
                </template>

                <div v-if="record.has_return_right" class="text-sm">
                    <Badge variant="secondary">
                        Direito a retorno
                        <template v-if="record.return_window_days">
                            em até {{ record.return_window_days }} dias
                        </template>
                    </Badge>
                </div>

                <div v-if="record.addenda.length > 0" class="grid gap-2">
                    <p class="text-muted-foreground text-sm font-medium">
                        Observações adicionais
                    </p>
                    <div
                        v-for="addendum in record.addenda"
                        :key="addendum.id"
                        class="rounded-md border p-3 text-sm"
                    >
                        <p class="whitespace-pre-line">{{ addendum.body }}</p>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{ formatDateTime(addendum.created_at) }}
                        </p>
                    </div>
                </div>

                <div v-if="record.files.length > 0" class="grid gap-1">
                    <p class="text-muted-foreground text-sm font-medium">
                        Documentos e arquivos
                    </p>
                    <ul class="text-sm">
                        <li
                            v-for="file in record.files"
                            :key="file.id"
                            class="flex items-center gap-2"
                        >
                            <Badge variant="outline">{{
                                file.category_label
                            }}</Badge>
                            {{ file.original_filename }}
                        </li>
                    </ul>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

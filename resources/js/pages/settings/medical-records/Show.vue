<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTimeBr } from '@/lib/masks';
import { dashboard } from '@/routes';
import {
    show as showFile,
    store as storeFile,
} from '@/routes/settings/medical-record-files';
import {
    addAddendum,
    finalize,
    release,
    update,
} from '@/routes/settings/medical-records';

type MedicalRecord = {
    id: string;
    status: 'draft' | 'finalized';
    status_label: string;
    patient_name: string | null;
    professional_name: string | null;
    anamnesis: string | null;
    preexisting_conditions: string | null;
    allergies: string | null;
    current_medications: string | null;
    contraindications: string | null;
    evaluation: string | null;
    treatment_plan: string | null;
    procedures_performed: string | null;
    evolution_notes: string | null;
    prescriptions: string | null;
    referrals: string | null;
    has_return_right: boolean;
    return_window_days: number | null;
    finalized_at: string | null;
    released_to_patient_at: string | null;
    addenda: {
        id: string;
        body: string;
        author_name: string | null;
        created_at: string;
    }[];
    files: {
        id: string;
        category: string;
        category_label: string;
        original_filename: string;
        uploaded_by_name: string | null;
        created_at: string;
    }[];
};

const props = defineProps<{
    appointment: {
        id: string;
        starts_at: string;
        status: string;
        status_label: string;
    };
    medicalRecord: MedicalRecord;
    canEdit: boolean;
    canFinalize: boolean;
    canRelease: boolean;
    canAddAddendum: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Prontuário' },
        ],
    },
});

const isDraft = computed(() => props.medicalRecord.status === 'draft');

const form = useForm({
    anamnesis: props.medicalRecord.anamnesis ?? '',
    preexisting_conditions: props.medicalRecord.preexisting_conditions ?? '',
    allergies: props.medicalRecord.allergies ?? '',
    current_medications: props.medicalRecord.current_medications ?? '',
    contraindications: props.medicalRecord.contraindications ?? '',
    evaluation: props.medicalRecord.evaluation ?? '',
    treatment_plan: props.medicalRecord.treatment_plan ?? '',
    procedures_performed: props.medicalRecord.procedures_performed ?? '',
    evolution_notes: props.medicalRecord.evolution_notes ?? '',
    prescriptions: props.medicalRecord.prescriptions ?? '',
    referrals: props.medicalRecord.referrals ?? '',
    has_return_right: props.medicalRecord.has_return_right,
    return_window_days: props.medicalRecord.return_window_days ?? 15,
});

function saveDraft() {
    form.patch(update(props.medicalRecord.id).url, { preserveScroll: true });
}

function finalizeRecord() {
    if (
        !confirm(
            'Finalizar o prontuário? Depois disso só será possível corrigir por adendo.',
        )
    ) {
        return;
    }

    form.patch(finalize(props.medicalRecord.id).url, { preserveScroll: true });
}

function releaseToPatient() {
    form.patch(release(props.medicalRecord.id).url, { preserveScroll: true });
}

const addendumForm = useForm({ body: '' });

function submitAddendum() {
    addendumForm.post(addAddendum(props.medicalRecord.id).url, {
        preserveScroll: true,
        onSuccess: () => addendumForm.reset(),
    });
}

const fileCategories: { value: string; label: string }[] = [
    { value: 'exam', label: 'Exame' },
    { value: 'clinical_photo', label: 'Fotografia clínica' },
    { value: 'prescription', label: 'Prescrição' },
    { value: 'certificate_or_declaration', label: 'Atestado ou declaração' },
    { value: 'consent', label: 'Consentimento' },
    { value: 'referral', label: 'Encaminhamento' },
    { value: 'report', label: 'Laudo' },
];

const fileForm = useForm<{ category: string; file: File | null }>({
    category: 'exam',
    file: null,
});
const fileInput = ref<HTMLInputElement | null>(null);

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    fileForm.file = target.files?.[0] ?? null;
}

function uploadFile() {
    fileForm.post(storeFile(props.medicalRecord.id).url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            fileForm.reset('file');

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}
</script>

<template>
    <Head title="Prontuário" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Prontuário"
            :description="`${medicalRecord.patient_name ?? ''} — ${formatDateTimeBr(appointment.starts_at)}`"
        />

        <div class="flex flex-wrap items-center gap-2">
            <Badge :variant="isDraft ? 'secondary' : 'default'">
                {{ medicalRecord.status_label }}
            </Badge>
            <Badge
                v-if="medicalRecord.released_to_patient_at"
                variant="outline"
            >
                Liberado ao paciente
            </Badge>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Anamnese e avaliação</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="mr-anamnesis">Anamnese</Label>
                    <Textarea
                        id="mr-anamnesis"
                        v-model="form.anamnesis"
                        :disabled="!isDraft"
                        rows="3"
                    />
                    <InputError :message="form.errors.anamnesis" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="mr-preexisting"
                            >Condições preexistentes</Label
                        >
                        <Textarea
                            id="mr-preexisting"
                            v-model="form.preexisting_conditions"
                            :disabled="!isDraft"
                            rows="2"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="mr-allergies">Alergias</Label>
                        <Textarea
                            id="mr-allergies"
                            v-model="form.allergies"
                            :disabled="!isDraft"
                            rows="2"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="mr-medications">Medicamentos em uso</Label>
                        <Textarea
                            id="mr-medications"
                            v-model="form.current_medications"
                            :disabled="!isDraft"
                            rows="2"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="mr-contraindications"
                            >Contraindicações</Label
                        >
                        <Textarea
                            id="mr-contraindications"
                            v-model="form.contraindications"
                            :disabled="!isDraft"
                            rows="2"
                        />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="mr-evaluation"
                        >Avaliação / hipótese diagnóstica</Label
                    >
                    <Textarea
                        id="mr-evaluation"
                        v-model="form.evaluation"
                        :disabled="!isDraft"
                        rows="3"
                    />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Tratamento e evolução</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="mr-plan">Plano de tratamento</Label>
                    <Textarea
                        id="mr-plan"
                        v-model="form.treatment_plan"
                        :disabled="!isDraft"
                        rows="3"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="mr-procedures">Procedimentos realizados</Label>
                    <Textarea
                        id="mr-procedures"
                        v-model="form.procedures_performed"
                        :disabled="!isDraft"
                        rows="3"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="mr-evolution"
                        >Evolução, intercorrências e orientações</Label
                    >
                    <Textarea
                        id="mr-evolution"
                        v-model="form.evolution_notes"
                        :disabled="!isDraft"
                        rows="3"
                    />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="mr-prescriptions">Prescrições</Label>
                        <Textarea
                            id="mr-prescriptions"
                            v-model="form.prescriptions"
                            :disabled="!isDraft"
                            rows="2"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="mr-referrals"
                            >Encaminhamentos / solicitações de exame</Label
                        >
                        <Textarea
                            id="mr-referrals"
                            v-model="form.referrals"
                            :disabled="!isDraft"
                            rows="2"
                        />
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <Label class="flex items-center gap-2">
                        <Checkbox
                            v-model:model-value="form.has_return_right"
                            :disabled="!isDraft"
                        />
                        Paciente tem direito a retorno
                    </Label>
                    <div
                        v-if="form.has_return_right"
                        class="grid max-w-40 gap-2"
                    >
                        <Label for="mr-return-window"
                            >Prazo para retorno (dias)</Label
                        >
                        <Input
                            id="mr-return-window"
                            v-model.number="form.return_window_days"
                            type="number"
                            min="1"
                            :disabled="!isDraft"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <div v-if="canEdit || canFinalize" class="flex flex-wrap gap-2">
            <Button
                v-if="canEdit"
                :disabled="form.processing"
                @click="saveDraft"
            >
                Salvar rascunho
            </Button>
            <Button
                v-if="canFinalize"
                variant="secondary"
                :disabled="form.processing"
                @click="finalizeRecord"
            >
                Finalizar prontuário
            </Button>
        </div>

        <Card v-if="!isDraft">
            <CardHeader>
                <CardTitle>Adendos</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4">
                <p
                    v-if="medicalRecord.addenda.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Nenhum adendo registrado.
                </p>
                <ul v-else class="grid gap-3">
                    <li
                        v-for="addendum in medicalRecord.addenda"
                        :key="addendum.id"
                        class="rounded-md border p-3 text-sm"
                    >
                        <p class="whitespace-pre-wrap">{{ addendum.body }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ addendum.author_name }} —
                            {{ formatDateTimeBr(addendum.created_at) }}
                        </p>
                    </li>
                </ul>

                <form
                    v-if="canAddAddendum"
                    class="grid gap-2"
                    @submit.prevent="submitAddendum"
                >
                    <Label for="mr-addendum">Adicionar adendo</Label>
                    <Textarea
                        id="mr-addendum"
                        v-model="addendumForm.body"
                        rows="2"
                    />
                    <InputError :message="addendumForm.errors.body" />
                    <Button
                        type="submit"
                        size="sm"
                        class="w-fit"
                        :disabled="
                            addendumForm.processing ||
                            addendumForm.body.trim() === ''
                        "
                    >
                        Adicionar
                    </Button>
                </form>

                <Button
                    v-if="canRelease && !medicalRecord.released_to_patient_at"
                    variant="outline"
                    size="sm"
                    class="w-fit"
                    @click="releaseToPatient"
                >
                    Liberar prontuário para o paciente
                </Button>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Arquivos</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4">
                <p
                    v-if="medicalRecord.files.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Nenhum arquivo anexado.
                </p>
                <ul v-else class="grid gap-2">
                    <li
                        v-for="file in medicalRecord.files"
                        :key="file.id"
                        class="flex items-center justify-between gap-2 rounded-md border p-3 text-sm"
                    >
                        <div>
                            <p class="font-medium">
                                {{ file.original_filename }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ file.category_label }} ·
                                {{ formatDateTimeBr(file.created_at) }}
                            </p>
                        </div>
                        <a
                            :href="
                                showFile({
                                    medicalRecord: medicalRecord.id,
                                    file: file.id,
                                }).url
                            "
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <Button variant="outline" size="sm">Ver</Button>
                        </a>
                    </li>
                </ul>

                <form
                    class="grid gap-2 sm:flex sm:items-end sm:gap-3"
                    @submit.prevent="uploadFile"
                >
                    <div class="grid gap-2">
                        <Label for="mr-file-category">Categoria</Label>
                        <Select v-model="fileForm.category">
                            <SelectTrigger id="mr-file-category" class="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="category in fileCategories"
                                    :key="category.value"
                                    :value="category.value"
                                >
                                    {{ category.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid min-w-0 flex-1 gap-2">
                        <Label for="mr-file">Arquivo (PDF, JPEG ou PNG)</Label>
                        <div class="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                class="flex shrink-0 items-center gap-2 rounded-md border border-dashed border-input px-3 py-2 text-sm text-muted-foreground transition-colors hover:border-primary hover:text-foreground"
                                @click="fileInput?.click()"
                            >
                                <Upload class="size-4" />
                                Selecionar arquivo
                            </button>
                            <span
                                v-if="fileForm.file"
                                :title="fileForm.file.name"
                                class="min-w-0 flex-1 truncate text-sm text-muted-foreground"
                            >
                                {{ fileForm.file.name }}
                            </span>
                        </div>
                        <input
                            id="mr-file"
                            ref="fileInput"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="hidden"
                            @change="onFileChange"
                        />
                        <InputError :message="fileForm.errors.file" />
                    </div>
                    <Button
                        type="submit"
                        size="sm"
                        class="shrink-0"
                        :disabled="fileForm.processing || !fileForm.file"
                    >
                        Enviar
                    </Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>

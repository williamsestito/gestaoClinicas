<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDateTimeBr } from '@/lib/masks';
import { patientHistory } from '@/routes/settings/medical-records';
import {
    edit as editPatient,
    summary as patientSummary,
} from '@/routes/settings/patients';

type SummaryResponse = {
    full_access: boolean;
    can_view_medical_record: boolean;
    patient: {
        id: string;
        name: string;
        preferred_name: string | null;
        phone: string | null;
        email: string | null;
        birth_date: string | null;
        document: string | null;
        status: string;
    };
    appointments_by_professional: {
        professional_name: string;
        appointments: {
            id: string;
            starts_at: string;
            status_label: string;
            service_name: string | null;
        }[];
    }[];
    pending_requests: {
        id: string;
        professional_name: string | null;
        status_label: string;
        created_at: string | null;
    }[];
};

/**
 * `null` fecha o modal — abrir passa o id do paciente. Usado tanto pela
 * tela admin ("Pacientes") quanto pelo autoatendimento ("Meus pacientes"),
 * inclusive para quem só tem acesso resumido (pré-agendamento pendente,
 * nunca atendeu de verdade) — o próprio endpoint decide o que devolver.
 */
const patientId = defineModel<string | null>({ default: null });

const loading = ref(false);
const failed = ref(false);
const data = ref<SummaryResponse | null>(null);

watch(patientId, async (id) => {
    data.value = null;
    failed.value = false;

    if (!id) {
        return;
    }

    loading.value = true;

    try {
        const response = await fetch(patientSummary(id).url, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            failed.value = true;

            return;
        }

        data.value = (await response.json()) as SummaryResponse;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
});

function close() {
    patientId.value = null;
}
</script>

<template>
    <Dialog
        :open="patientId !== null"
        @update:open="(open) => !open && close()"
    >
        <DialogContent v-if="patientId" class="max-h-[80vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    {{
                        data?.patient.preferred_name ||
                        data?.patient.name ||
                        'Paciente'
                    }}
                </DialogTitle>
                <DialogDescription v-if="data">
                    {{ data.patient.phone ?? '—' }}
                    <template v-if="data.patient.email">
                        · {{ data.patient.email }}</template
                    >
                </DialogDescription>
            </DialogHeader>

            <p v-if="loading" class="text-muted-foreground text-sm">
                Carregando…
            </p>
            <p v-else-if="failed" class="text-destructive text-sm">
                Não foi possível carregar os detalhes deste paciente.
            </p>

            <template v-else-if="data">
                <p
                    v-if="!data.full_access"
                    class="bg-muted text-muted-foreground rounded-md border px-3 py-2 text-sm"
                >
                    Este paciente ainda não foi atendido por você — só há um
                    pré-agendamento pendente. O cadastro completo e o prontuário
                    ficam disponíveis depois do primeiro atendimento.
                </p>

                <div
                    v-if="data.pending_requests.length"
                    class="grid gap-2 py-2"
                >
                    <h3 class="text-sm font-medium">
                        Pré-agendamentos pendentes
                    </h3>
                    <ul class="grid gap-1 text-sm">
                        <li
                            v-for="request in data.pending_requests"
                            :key="request.id"
                            class="flex items-center justify-between rounded-md border p-2"
                        >
                            <span>{{
                                request.professional_name ??
                                'Profissional não definido'
                            }}</span>
                            <Badge variant="outline">{{
                                request.status_label
                            }}</Badge>
                        </li>
                    </ul>
                </div>

                <div
                    v-if="data.appointments_by_professional.length"
                    class="grid gap-3 py-2"
                >
                    <h3 class="text-sm font-medium">
                        Histórico de atendimentos
                    </h3>
                    <div
                        v-for="group in data.appointments_by_professional"
                        :key="group.professional_name"
                        class="grid gap-1"
                    >
                        <p class="text-muted-foreground text-sm font-medium">
                            {{ group.professional_name }}
                        </p>
                        <ul class="grid gap-1 text-sm">
                            <li
                                v-for="appointment in group.appointments"
                                :key="appointment.id"
                                class="flex items-center justify-between rounded-md border p-2"
                            >
                                <span
                                    >{{
                                        formatDateTimeBr(appointment.starts_at)
                                    }}
                                    —
                                    {{
                                        appointment.service_name ??
                                        'Serviço não informado'
                                    }}</span
                                >
                                <Badge variant="outline">{{
                                    appointment.status_label
                                }}</Badge>
                            </li>
                        </ul>
                    </div>
                </div>

                <p
                    v-if="
                        !data.pending_requests.length &&
                        !data.appointments_by_professional.length
                    "
                    class="text-muted-foreground text-sm"
                >
                    Nenhum agendamento ou pré-agendamento registrado ainda.
                </p>
            </template>

            <DialogFooter class="flex-wrap gap-2">
                <Button variant="outline" @click="close">Fechar</Button>
                <Link
                    v-if="data?.full_access"
                    :href="editPatient(patientId).url"
                >
                    <Button variant="outline">Editar cadastro completo</Button>
                </Link>
                <Link
                    v-if="data?.can_view_medical_record"
                    :href="patientHistory(patientId).url"
                >
                    <Button variant="outline">Prontuário</Button>
                </Link>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

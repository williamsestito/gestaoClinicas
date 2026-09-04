<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import PatientSearchSelect from '@/components/appointments/PatientSearchSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTimeBr } from '@/lib/masks';
import { store as storeAppointment } from '@/routes/settings/appointments';
import type { AppointmentRequestSummary } from '@/types/site';

/**
 * Compartilhado entre "Meus pré-agendamentos" (profissional, sempre o
 * próprio) e "Solicitações de agendamento" (admin/atendimento, qualquer
 * profissional da organização — `request.professional_id` já vem
 * resolvido do backend em ambos os casos). Só é oferecido pela tela que
 * monta este componente quando o lead tem unidade/serviço/horário exatos
 * (ver canInstantSchedule() em cada página).
 */
const props = defineProps<{
    request: AppointmentRequestSummary | null;
}>();

const emit = defineEmits<{ close: [] }>();

const form = useForm({
    patient_id: '',
    unit_id: '',
    professional_id: '',
    service_id: '',
    starts_at: '',
    appointment_request_id: '',
    notes: '',
});

watch(
    () => props.request,
    (request) => {
        form.clearErrors();

        if (!request) {
            form.reset();

            return;
        }

        form.patient_id = request.patient_id ?? '';
        form.unit_id = request.unit_id ?? '';
        form.professional_id = request.professional_id ?? '';
        form.service_id = request.preferred_service_id ?? '';
        form.starts_at = request.preferred_starts_at ?? '';
        form.appointment_request_id = request.id;
        form.notes = request.notes ?? '';
    },
);

function close() {
    emit('close');
}

function confirm() {
    if (!props.request) {
        return;
    }

    form.post(storeAppointment().url, {
        preserveScroll: true,
        onSuccess: () => close(),
    });
}

function formatDate(value: string | null): string {
    return value ? formatDateTimeBr(value) : '—';
}
</script>

<template>
    <Dialog :open="request !== null" @update:open="(open) => !open && close()">
        <DialogContent v-if="request">
            <DialogHeader>
                <DialogTitle>Confirmar agendamento</DialogTitle>
                <DialogDescription>
                    Os dados abaixo vêm do pré-agendamento — confira e confirme
                    para criar o atendimento na agenda.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-1 text-sm">
                    <p v-if="request.professional_name">
                        <span class="text-muted-foreground">Profissional:</span>
                        {{ request.professional_name }}
                    </p>
                    <p>
                        <span class="text-muted-foreground">Serviço:</span>
                        {{ request.preferred_service_name }}
                    </p>
                    <p>
                        <span class="text-muted-foreground">Unidade:</span>
                        {{ request.unit_name }}
                    </p>
                    <p>
                        <span class="text-muted-foreground">Data/hora:</span>
                        {{ formatDate(request.preferred_starts_at) }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label>Paciente</Label>
                    <p
                        v-if="request.patient_id"
                        class="rounded-md border bg-muted px-3 py-2 text-sm"
                    >
                        {{ request.patient_name }}
                    </p>
                    <PatientSearchSelect
                        v-else
                        v-model="form.patient_id"
                        :error="form.errors.patient_id"
                        :prefill-for-new-patient="{
                            name: request.name,
                            phone: request.phone,
                            email: request.email ?? undefined,
                            document: request.document ?? undefined,
                        }"
                    />
                </div>

                <InputError :message="form.errors.starts_at" />
                <InputError :message="form.errors.appointment_request_id" />
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="form.processing"
                    @click="close()"
                >
                    Cancelar
                </Button>
                <Button
                    :disabled="!form.patient_id || form.processing"
                    @click="confirm()"
                >
                    <Spinner v-if="form.processing" />
                    Confirmar agendamento
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

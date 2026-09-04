<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import patientPortal from '@/routes/patient-portal';

type AppointmentRow = {
    id: string;
    starts_at: string;
    ends_at: string;
    status: string;
    status_label: string;
    professional_name: string;
    service_name: string;
    unit_name: string;
};

type PendingRequestRow = {
    id: string;
    created_at: string;
    status: string;
    status_label: string;
    professional_name: string | null;
    service_name: string | null;
    preferred_date: string | null;
    preferred_period: string | null;
    notes: string | null;
};

const props = defineProps<{
    patient: { id: string; name: string };
    appointments: AppointmentRow[];
    pendingRequests: PendingRequestRow[];
}>();

defineOptions({
    layout: {
        title: 'Meus agendamentos',
    },
});

function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'cancelled' || status === 'no_show') {
        return 'destructive';
    }

    if (status === 'completed') {
        return 'secondary';
    }

    if (status === 'requested' || status === 'awaiting_confirmation') {
        return 'outline';
    }

    return 'default';
}

// Status de App\Enums\AppointmentRequestStatus — conjunto de valores
// diferente do de AppointmentStatus (statusVariant() acima), embora
// "cancelled" apareça nos dois.
function pendingRequestStatusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'cancelled') {
        return 'destructive';
    }

    if (status === 'contacted') {
        return 'secondary';
    }

    return 'outline';
}

function formatPreferredDate(dateOnly: string): string {
    return new Date(`${dateOnly}T00:00:00`).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function canCancelOrReschedule(status: string): boolean {
    return (
        status === 'requested' ||
        status === 'confirmed' ||
        status === 'checked_in'
    );
}

function cancelAppointment(appointment: AppointmentRow, reason: string) {
    router.patch(
        patientPortal.appointments.cancel([props.patient.id, appointment.id])
            .url,
        { reason },
        { preserveScroll: true },
    );
}

const appointmentToCancel = ref<AppointmentRow | null>(null);
const cancelReason = ref('');

function requestCancelAppointment(appointment: AppointmentRow) {
    appointmentToCancel.value = appointment;
    cancelReason.value = '';
}

function confirmCancelAppointment() {
    if (!appointmentToCancel.value || !cancelReason.value.trim()) {
        return;
    }

    cancelAppointment(appointmentToCancel.value, cancelReason.value.trim());
    appointmentToCancel.value = null;
}

function acceptProposedTime(appointment: AppointmentRow) {
    router.patch(
        patientPortal.appointments.acceptProposedTime([
            props.patient.id,
            appointment.id,
        ]).url,
        {},
        { preserveScroll: true },
    );
}

function declineProposedTime(appointment: AppointmentRow) {
    cancelAppointment(appointment, 'Horário proposto recusado');
}

const activePendingRequests = computed(() =>
    props.pendingRequests.filter((request) => request.status !== 'cancelled'),
);

const cancelledPendingRequests = computed(() =>
    props.pendingRequests.filter((request) => request.status === 'cancelled'),
);

const showCancelledHistory = ref(false);

const pendingRequestToCancel = ref<PendingRequestRow | null>(null);

function requestCancelPendingRequest(request: PendingRequestRow) {
    pendingRequestToCancel.value = request;
}

function confirmCancelPendingRequest() {
    if (!pendingRequestToCancel.value) {
        return;
    }

    router.patch(
        patientPortal.appointmentRequests.cancel([
            props.patient.id,
            pendingRequestToCancel.value.id,
        ]).url,
        {},
        { preserveScroll: true },
    );
    pendingRequestToCancel.value = null;
}
</script>

<template>
    <Head title="Meus agendamentos" />

    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-medium">
                Agendamentos de {{ patient.name }}
            </h1>
            <Button as-child size="sm">
                <Link :href="patientPortal.appointments.create(patient.id)">
                    Novo agendamento
                </Link>
            </Button>
        </div>

        <div
            v-if="
                activePendingRequests.length > 0 ||
                cancelledPendingRequests.length > 0
            "
            class="grid gap-3"
        >
            <h2
                v-if="activePendingRequests.length > 0"
                class="text-muted-foreground text-sm font-medium"
            >
                Pré-agendamentos aguardando confirmação
            </h2>
            <Card v-for="request in activePendingRequests" :key="request.id">
                <CardContent
                    class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="font-medium">
                            {{
                                request.service_name ?? 'Serviço não informado'
                            }}
                        </p>
                        <p
                            v-if="request.professional_name"
                            class="text-muted-foreground text-sm"
                        >
                            Com {{ request.professional_name }}
                        </p>
                        <p
                            v-if="
                                request.preferred_date ||
                                request.preferred_period
                            "
                            class="text-muted-foreground text-sm"
                        >
                            Preferência:
                            <template v-if="request.preferred_date">
                                {{
                                    formatPreferredDate(request.preferred_date)
                                }}
                            </template>
                            <template v-if="request.preferred_period">
                                · {{ request.preferred_period }}
                            </template>
                        </p>
                    </div>

                    <div class="flex flex-col items-end gap-2">
                        <Badge
                            :variant="
                                pendingRequestStatusVariant(request.status)
                            "
                        >
                            {{ request.status_label }}
                        </Badge>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="requestCancelPendingRequest(request)"
                        >
                            Cancelar
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div v-if="cancelledPendingRequests.length > 0" class="grid gap-3">
                <Button
                    variant="ghost"
                    size="sm"
                    class="w-fit"
                    @click="showCancelledHistory = !showCancelledHistory"
                >
                    {{
                        showCancelledHistory
                            ? 'Ocultar histórico'
                            : `Ver histórico (${cancelledPendingRequests.length})`
                    }}
                </Button>

                <template v-if="showCancelledHistory">
                    <Card
                        v-for="request in cancelledPendingRequests"
                        :key="request.id"
                        class="opacity-70"
                    >
                        <CardContent
                            class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p class="font-medium">
                                    {{
                                        request.service_name ??
                                        'Serviço não informado'
                                    }}
                                </p>
                                <p
                                    v-if="request.professional_name"
                                    class="text-muted-foreground text-sm"
                                >
                                    Com {{ request.professional_name }}
                                </p>
                            </div>

                            <Badge
                                :variant="
                                    pendingRequestStatusVariant(request.status)
                                "
                            >
                                {{ request.status_label }}
                            </Badge>
                        </CardContent>
                    </Card>
                </template>
            </div>
        </div>

        <EmptyState
            v-if="appointments.length === 0 && pendingRequests.length === 0"
            title="Nenhum agendamento ainda"
            description="Crie uma solicitação de agendamento — a clínica confirma o horário em seguida."
        />

        <div v-if="appointments.length > 0" class="grid gap-3">
            <h2
                v-if="pendingRequests.length > 0"
                class="text-muted-foreground text-sm font-medium"
            >
                Agendamentos confirmados
            </h2>
            <Card v-for="appointment in appointments" :key="appointment.id">
                <CardContent
                    class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="font-medium">
                            {{ formatDateTime(appointment.starts_at) }}
                        </p>
                        <p class="text-muted-foreground text-sm">
                            {{ appointment.service_name }} ·
                            {{ appointment.professional_name }} ·
                            {{ appointment.unit_name }}
                        </p>
                        <p
                            v-if="
                                appointment.status === 'awaiting_confirmation'
                            "
                            class="text-muted-foreground text-sm"
                        >
                            A clínica propôs este novo horário — confirme ou
                            recuse abaixo.
                        </p>
                    </div>

                    <div class="flex flex-col items-end gap-2">
                        <Badge :variant="statusVariant(appointment.status)">
                            {{ appointment.status_label }}
                        </Badge>

                        <div
                            v-if="
                                appointment.status === 'awaiting_confirmation'
                            "
                            class="flex gap-2"
                        >
                            <Button
                                size="sm"
                                @click="acceptProposedTime(appointment)"
                            >
                                Aceitar
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                @click="declineProposedTime(appointment)"
                            >
                                Recusar
                            </Button>
                        </div>

                        <div
                            v-else-if="
                                canCancelOrReschedule(appointment.status)
                            "
                            class="flex gap-2"
                        >
                            <Link
                                :href="
                                    patientPortal.appointments.reschedule.edit([
                                        patient.id,
                                        appointment.id,
                                    ])
                                "
                            >
                                <Button variant="outline" size="sm">
                                    Reagendar
                                </Button>
                            </Link>
                            <Button
                                variant="outline"
                                size="sm"
                                @click="requestCancelAppointment(appointment)"
                            >
                                Cancelar
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Dialog
            :open="!!pendingRequestToCancel"
            @update:open="
                (open) => {
                    if (!open) pendingRequestToCancel = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancelar pré-agendamento?</DialogTitle>
                    <DialogDescription>
                        A solicitação
                        <template
                            v-if="pendingRequestToCancel?.professional_name"
                        >
                            com {{ pendingRequestToCancel.professional_name }}
                        </template>
                        será cancelada. Você pode criar uma nova solicitação
                        quando quiser.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button
                        variant="secondary"
                        @click="pendingRequestToCancel = null"
                    >
                        Voltar
                    </Button>
                    <Button
                        variant="destructive"
                        @click="confirmCancelPendingRequest"
                    >
                        Cancelar solicitação
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="!!appointmentToCancel"
            @update:open="
                (open) => {
                    if (!open) appointmentToCancel = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancelar agendamento?</DialogTitle>
                    <DialogDescription>
                        Conte pra gente o motivo do cancelamento — isso ajuda a
                        clínica a organizar a agenda.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="cancel-reason" class="sr-only">
                        Motivo do cancelamento
                    </Label>
                    <Textarea
                        id="cancel-reason"
                        v-model="cancelReason"
                        placeholder="Motivo do cancelamento"
                        rows="3"
                    />
                </div>
                <DialogFooter class="gap-2">
                    <Button
                        variant="secondary"
                        @click="appointmentToCancel = null"
                    >
                        Voltar
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="!cancelReason.trim()"
                        @click="confirmCancelAppointment"
                    >
                        Cancelar agendamento
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

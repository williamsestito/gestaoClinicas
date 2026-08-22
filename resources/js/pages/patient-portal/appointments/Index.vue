<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

const props = defineProps<{
    patient: { id: string; name: string };
    appointments: AppointmentRow[];
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

function doCancel(appointment: AppointmentRow) {
    const reason = prompt('Motivo do cancelamento:');

    if (!reason) {
        return;
    }

    cancelAppointment(appointment, reason);
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

        <EmptyState
            v-if="appointments.length === 0"
            title="Nenhum agendamento ainda"
            description="Crie uma solicitação de agendamento — a clínica confirma o horário em seguida."
        />

        <div v-else class="grid gap-3">
            <Card v-for="appointment in appointments" :key="appointment.id">
                <CardContent
                    class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="font-medium">
                            {{ formatDateTime(appointment.starts_at) }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ appointment.service_name }} ·
                            {{ appointment.professional_name }} ·
                            {{ appointment.unit_name }}
                        </p>
                        <p
                            v-if="
                                appointment.status === 'awaiting_confirmation'
                            "
                            class="text-sm text-muted-foreground"
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
                                @click="doCancel(appointment)"
                            >
                                Cancelar
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Repeat } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import {
    cancel,
    checkIn,
    complete,
    confirm,
    create,
    index,
    noShow,
    start,
} from '@/routes/settings/appointments';
import { edit as editPropose } from '@/routes/settings/appointments/propose';
import { index as waitlistIndex } from '@/routes/settings/appointments/waitlist';

type AppointmentRow = {
    id: string;
    starts_at: string;
    ends_at: string;
    status: string;
    status_label: string;
    professional_name: string;
    patient_name: string;
    service_name: string;
    unit_name: string;
    cancellation_reason: string | null;
    is_recurring: boolean;
};

const props = defineProps<{
    appointments: AppointmentRow[];
    professionals: { id: string; display_name: string }[];
    filters: { date: string; professional_id: string | null };
}>();

const date = ref(props.filters.date);
const professionalId = ref(props.filters.professional_id ?? '');

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Agenda' },
        ],
    },
});

function applyFilters() {
    router.get(
        index().url,
        {
            date: date.value || undefined,
            professional_id: professionalId.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function doConfirm(appointment: AppointmentRow) {
    router.patch(confirm(appointment.id).url, {}, { preserveScroll: true });
}

function doCheckIn(appointment: AppointmentRow) {
    router.patch(checkIn(appointment.id).url, {}, { preserveScroll: true });
}

function doStart(appointment: AppointmentRow) {
    router.patch(start(appointment.id).url, {}, { preserveScroll: true });
}

function doComplete(appointment: AppointmentRow) {
    router.patch(complete(appointment.id).url, {}, { preserveScroll: true });
}

function doNoShow(appointment: AppointmentRow) {
    router.patch(noShow(appointment.id).url, {}, { preserveScroll: true });
}

function doCancel(appointment: AppointmentRow) {
    const reason = prompt('Motivo do cancelamento:');

    if (!reason) {
        return;
    }

    router.patch(
        cancel(appointment.id).url,
        { reason },
        { preserveScroll: true },
    );
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

    return 'default';
}
</script>

<template>
    <Head title="Agenda" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Agenda"
            description="Agendamentos reais da clínica, por dia."
        >
            <template #actions>
                <Button as-child variant="outline">
                    <Link :href="waitlistIndex().url">Lista de espera</Link>
                </Button>
                <Button as-child>
                    <Link :href="create().url">Novo agendamento</Link>
                </Button>
            </template>
        </PageHeader>

        <form
            class="flex flex-col gap-3 sm:flex-row sm:items-end"
            @submit.prevent="applyFilters"
        >
            <div class="grid gap-2">
                <label
                    for="appointments-date"
                    class="text-sm font-medium text-muted-foreground"
                >
                    Data
                </label>
                <Input
                    id="appointments-date"
                    v-model="date"
                    type="date"
                    @change="applyFilters"
                />
            </div>
            <div class="grid gap-2">
                <label
                    for="appointments-professional"
                    class="text-sm font-medium text-muted-foreground"
                >
                    Profissional
                </label>
                <select
                    id="appointments-professional"
                    v-model="professionalId"
                    class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    @change="applyFilters"
                >
                    <option value="">Todos</option>
                    <option
                        v-for="professional in professionals"
                        :key="professional.id"
                        :value="professional.id"
                    >
                        {{ professional.display_name }}
                    </option>
                </select>
            </div>
        </form>

        <EmptyState
            v-if="appointments.length === 0"
            title="Nenhum agendamento neste dia"
            description="Ajuste os filtros ou crie um novo agendamento."
        />

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3">Horário</th>
                        <th class="px-4 py-3">Paciente</th>
                        <th class="px-4 py-3">Profissional</th>
                        <th class="px-4 py-3">Serviço</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="appointment in appointments" :key="appointment.id">
                        <td class="px-4 py-3">
                            {{ formatTime(appointment.starts_at) }}–{{
                                formatTime(appointment.ends_at)
                            }}
                        </td>
                        <td class="px-4 py-3">
                            {{ appointment.patient_name }}
                            <Repeat
                                v-if="appointment.is_recurring"
                                class="inline size-3.5 text-muted-foreground"
                                aria-label="Agendamento recorrente"
                            />
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ appointment.professional_name }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ appointment.service_name }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge :variant="statusVariant(appointment.status)">
                                {{ appointment.status_label }}
                            </Badge>
                            <p
                                v-if="appointment.cancellation_reason"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ appointment.cancellation_reason }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-2">
                                <Button
                                    v-if="appointment.status === 'requested'"
                                    variant="outline"
                                    size="sm"
                                    @click="doConfirm(appointment)"
                                >
                                    Confirmar
                                </Button>
                                <Button
                                    v-if="appointment.status === 'requested'"
                                    as-child
                                    variant="outline"
                                    size="sm"
                                >
                                    <Link :href="editPropose(appointment.id).url">
                                        Propor outro horário
                                    </Link>
                                </Button>
                                <Button
                                    v-if="appointment.status === 'confirmed'"
                                    variant="outline"
                                    size="sm"
                                    @click="doCheckIn(appointment)"
                                >
                                    Check-in
                                </Button>
                                <Button
                                    v-if="appointment.status === 'checked_in'"
                                    variant="outline"
                                    size="sm"
                                    @click="doStart(appointment)"
                                >
                                    Iniciar
                                </Button>
                                <Button
                                    v-if="appointment.status === 'in_progress'"
                                    variant="outline"
                                    size="sm"
                                    @click="doComplete(appointment)"
                                >
                                    Concluir
                                </Button>
                                <Button
                                    v-if="
                                        ['confirmed', 'checked_in'].includes(
                                            appointment.status,
                                        )
                                    "
                                    variant="outline"
                                    size="sm"
                                    @click="doNoShow(appointment)"
                                >
                                    Não compareceu
                                </Button>
                                <Button
                                    v-if="
                                        !['completed', 'cancelled', 'no_show'].includes(
                                            appointment.status,
                                        )
                                    "
                                    variant="outline"
                                    size="sm"
                                    @click="doCancel(appointment)"
                                >
                                    {{
                                        appointment.status === 'requested'
                                            ? 'Recusar solicitação'
                                            : 'Cancelar'
                                    }}
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

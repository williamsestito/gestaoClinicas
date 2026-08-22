<script lang="ts">
export type ProfessionalDashboardData = {
    period: 'day' | 'week' | 'month';
    referenceDate: string;
    rangeLabel: string;
    counters: { open: number; scheduled: number; completed: number };
    agenda: {
        id: string;
        starts_at: string;
        ends_at: string;
        status: string;
        status_label: string;
        patient_name: string;
        service_name: string;
        unit_name: string;
    }[];
    agendaTruncated: boolean;
    pendingAppointmentRequestsCount: number;
    pendingAppointmentRequests: {
        id: string;
        name: string;
        phone: string;
        service_name: string | null;
        created_at: string | null;
    }[];
    reminders: {
        id: string;
        body: string;
        color: 'yellow' | 'pink' | 'blue' | 'green';
        created_at: string;
    }[];
};
</script>

<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Plus, X } from '@lucide/vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import {
    destroy as destroyReminder,
    store as storeReminder,
} from '@/routes/dashboard/reminders';
import {
    checkIn as checkInAppointment,
    complete as completeAppointment,
    confirm as confirmAppointment,
    noShow as noShowAppointment,
    start as startAppointment,
} from '@/routes/settings/appointments';
import { index as myAppointmentRequests } from '@/routes/settings/my-appointment-requests';

type Period = ProfessionalDashboardData['period'];
type Reminder = ProfessionalDashboardData['reminders'][number];

const props = defineProps<{
    data: ProfessionalDashboardData;
}>();

const PERIOD_LABELS: Record<Period, string> = {
    day: 'Dia',
    week: 'Semana',
    month: 'Mês',
};

const REMINDER_COLORS: Record<Reminder['color'], string> = {
    yellow: 'bg-yellow-100 border-yellow-300 dark:bg-yellow-950/40 dark:border-yellow-800',
    pink: 'bg-pink-100 border-pink-300 dark:bg-pink-950/40 dark:border-pink-800',
    blue: 'bg-blue-100 border-blue-300 dark:bg-blue-950/40 dark:border-blue-800',
    green: 'bg-green-100 border-green-300 dark:bg-green-950/40 dark:border-green-800',
};

function reload(params: { period?: Period; date?: string }) {
    router.get(
        dashboard().url,
        {
            period: params.period ?? props.data.period,
            date: params.date ?? props.data.referenceDate,
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['professionalDashboard'],
        },
    );
}

function shiftDate(direction: 1 | -1): string {
    const date = new Date(`${props.data.referenceDate}T00:00:00`);

    if (props.data.period === 'day') {
        date.setDate(date.getDate() + direction);
    } else if (props.data.period === 'week') {
        date.setDate(date.getDate() + direction * 7);
    } else {
        date.setMonth(date.getMonth() + direction);
    }

    return date.toISOString().slice(0, 10);
}

function goToPrevious() {
    reload({ date: shiftDate(-1) });
}

function goToNext() {
    reload({ date: shiftDate(1) });
}

function goToToday() {
    reload({ date: new Date().toISOString().slice(0, 10) });
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

function formatTime(value: string): string {
    return new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
    }).format(new Date(`${value}T00:00:00`));
}

function isPast(isoDate: string): boolean {
    return new Date(isoDate).getTime() < Date.now();
}

// Reaproveita as mesmas rotas/Actions/AppointmentPolicy do staff — a
// diferença é só de autorização (AppointmentsManageOwn, restrita ao próprio
// agendamento). O controller redireciona de volta com back(), então a
// página recarrega por completo (o `only` do reload() acima não se aplica
// a um redirect). O paciente, ao abrir a própria tela, lê a mesma linha de
// Appointment já atualizada — sem sincronização nenhuma de nossa parte.
function runAppointmentAction(url: string) {
    router.patch(url, {}, { preserveScroll: true });
}

const reminderForm = useForm({
    body: '',
    color: 'yellow' as Reminder['color'],
});

function submitReminder() {
    reminderForm.post(storeReminder().url, {
        preserveScroll: true,
        onSuccess: () => reminderForm.reset(),
    });
}

function removeReminder(reminder: Reminder) {
    router.delete(destroyReminder(reminder.id).url, { preserveScroll: true });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            v-if="data.pendingAppointmentRequestsCount > 0"
            class="flex flex-col gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30"
            role="status"
        >
            <div class="flex items-start gap-3">
                <AlertTriangle
                    class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400"
                />
                <div class="flex-1">
                    <p class="font-medium text-amber-900 dark:text-amber-200">
                        {{ data.pendingAppointmentRequestsCount }}
                        {{
                            data.pendingAppointmentRequestsCount === 1
                                ? 'novo pré-agendamento aguardando contato'
                                : 'novos pré-agendamentos aguardando contato'
                        }}
                    </p>
                    <ul
                        class="mt-2 grid gap-1 text-sm text-amber-800 dark:text-amber-300"
                    >
                        <li
                            v-for="request in data.pendingAppointmentRequests"
                            :key="request.id"
                        >
                            {{ request.name }} — {{ request.phone }}
                            <span v-if="request.service_name"
                                >({{ request.service_name }})</span
                            >
                        </li>
                    </ul>
                    <TextLink
                        :href="myAppointmentRequests()"
                        class="mt-2 inline-block text-sm font-medium"
                    >
                        Ver todos em "Meus pré-agendamentos"
                    </TextLink>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Em aberto</CardDescription>
                    <CardTitle class="text-3xl">{{
                        data.counters.open
                    }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Agendados</CardDescription>
                    <CardTitle class="text-3xl">{{
                        data.counters.scheduled
                    }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Executados</CardDescription>
                    <CardTitle class="text-3xl">{{
                        data.counters.completed
                    }}</CardTitle>
                </CardHeader>
            </Card>
        </div>

        <Card>
            <CardHeader
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <CardTitle>Agenda</CardTitle>
                    <CardDescription>{{ data.rangeLabel }}</CardDescription>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex overflow-hidden rounded-md border">
                        <Button
                            v-for="period in [
                                'day',
                                'week',
                                'month',
                            ] as Period[]"
                            :key="period"
                            type="button"
                            size="sm"
                            :variant="
                                data.period === period ? 'default' : 'ghost'
                            "
                            class="rounded-none"
                            @click="reload({ period })"
                        >
                            {{ PERIOD_LABELS[period] }}
                        </Button>
                    </div>
                    <div class="flex items-center gap-1">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="goToPrevious"
                            >Anterior</Button
                        >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="goToToday"
                            >Hoje</Button
                        >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="goToNext"
                            >Próximo</Button
                        >
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <p
                    v-if="data.agenda.length === 0"
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    Nenhum agendamento neste período.
                </p>
                <ul v-else class="grid gap-2">
                    <li
                        v-for="appointment in data.agenda"
                        :key="appointment.id"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3 text-sm"
                    >
                        <div class="flex flex-col">
                            <span class="font-medium">
                                {{
                                    data.period === 'day'
                                        ? ''
                                        : formatDate(
                                              appointment.starts_at.slice(
                                                  0,
                                                  10,
                                              ),
                                          ) + ' — '
                                }}{{ formatTime(appointment.starts_at) }}–{{
                                    formatTime(appointment.ends_at)
                                }}
                            </span>
                            <span class="text-muted-foreground">
                                {{ appointment.patient_name }} ·
                                {{ appointment.service_name }} ·
                                {{ appointment.unit_name }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge :variant="statusVariant(appointment.status)">
                                {{ appointment.status_label }}
                            </Badge>
                            <Button
                                v-if="appointment.status === 'requested'"
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="
                                    runAppointmentAction(
                                        confirmAppointment(appointment.id).url,
                                    )
                                "
                                >Confirmar</Button
                            >
                            <template
                                v-else-if="appointment.status === 'confirmed'"
                            >
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="
                                        runAppointmentAction(
                                            checkInAppointment(appointment.id)
                                                .url,
                                        )
                                    "
                                    >Check-in</Button
                                >
                                <Button
                                    v-if="isPast(appointment.starts_at)"
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="
                                        runAppointmentAction(
                                            noShowAppointment(appointment.id)
                                                .url,
                                        )
                                    "
                                    >Não compareceu</Button
                                >
                            </template>
                            <Button
                                v-else-if="appointment.status === 'checked_in'"
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="
                                    runAppointmentAction(
                                        startAppointment(appointment.id).url,
                                    )
                                "
                                >Iniciar atendimento</Button
                            >
                            <Button
                                v-else-if="appointment.status === 'in_progress'"
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="
                                    runAppointmentAction(
                                        completeAppointment(appointment.id).url,
                                    )
                                "
                                >Concluir</Button
                            >
                        </div>
                    </li>
                </ul>
                <p
                    v-if="data.agendaTruncated"
                    class="mt-2 text-xs text-muted-foreground"
                >
                    Mostrando os primeiros agendamentos do período — reduza o
                    período para ver a lista completa.
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Avisos e lembretes</CardTitle>
                <CardDescription
                    >Anotações pessoais, visíveis só para você.</CardDescription
                >
            </CardHeader>
            <CardContent class="grid gap-4">
                <form
                    class="flex flex-col gap-2 sm:flex-row sm:items-start"
                    @submit.prevent="submitReminder"
                >
                    <Textarea
                        v-model="reminderForm.body"
                        placeholder="Escreva um lembrete..."
                        rows="2"
                        maxlength="280"
                        class="sm:flex-1"
                    />
                    <div class="flex items-center gap-2">
                        <Select v-model="reminderForm.color">
                            <SelectTrigger class="w-28">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="yellow">Amarelo</SelectItem>
                                <SelectItem value="pink">Rosa</SelectItem>
                                <SelectItem value="blue">Azul</SelectItem>
                                <SelectItem value="green">Verde</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button
                            type="submit"
                            size="icon"
                            :disabled="
                                reminderForm.processing ||
                                reminderForm.body.trim() === ''
                            "
                            aria-label="Adicionar lembrete"
                        >
                            <Plus class="size-4" />
                        </Button>
                    </div>
                </form>

                <p
                    v-if="data.reminders.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Nenhum lembrete ainda.
                </p>
                <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="reminder in data.reminders"
                        :key="reminder.id"
                        :class="[
                            'relative rounded-md border p-3 text-sm shadow-sm',
                            REMINDER_COLORS[reminder.color],
                        ]"
                    >
                        <button
                            type="button"
                            class="absolute top-1 right-1 rounded p-1 text-muted-foreground hover:text-foreground"
                            aria-label="Remover lembrete"
                            @click="removeReminder(reminder)"
                        >
                            <X class="size-3.5" />
                        </button>
                        <p class="pr-4 whitespace-pre-wrap">
                            {{ reminder.body }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

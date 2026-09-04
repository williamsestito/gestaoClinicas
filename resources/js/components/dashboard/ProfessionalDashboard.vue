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
        medical_record_id: string | null;
    }[];
    agendaTruncated: boolean;
    completedWithoutMedicalRecordCount: number;
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
        alarm_at: string | null;
        created_at: string;
    }[];
};
</script>

<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { AlarmClock, AlertTriangle, CalendarDays, Plus, X } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
    dismissAlarm as dismissReminderAlarm,
    store as storeReminder,
} from '@/routes/dashboard/reminders';
import {
    checkIn as checkInAppointment,
    complete as completeAppointment,
    confirm as confirmAppointment,
    noShow as noShowAppointment,
    start as startAppointment,
} from '@/routes/settings/appointments';
import { show as showMedicalRecord } from '@/routes/settings/medical-records';
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

/**
 * Clique nos cartões "Em aberto"/"Agendados"/"Executados" — leva até a
 * seção Agenda da mesma página (não existe tela cheia dedicada para o
 * profissional), ajustando para o mês atual para aumentar a chance de o
 * item relevante já aparecer na lista abaixo sem precisar navegar mais.
 */
function goToAgenda() {
    const today = new Date().toISOString().slice(0, 10);

    reload({ period: 'month', date: today });

    nextTick(() => {
        document
            .getElementById('agenda')
            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
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

// Escolha direta de data (em vez de só andar um dia/semana/mês por vez com
// Anterior/Próximo) — o mesmo campo serve para pular para uma semana ou mês
// específico: basta escolher qualquer dia dentro do período desejado, já
// que o backend calcula o intervalo a partir de `referenceDate`
// (ver DashboardController::periodRange()).
function goToDate(value: string) {
    if (value) {
        reload({ date: value });
    }
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

// Agrupamento por dia (para o calendário do mês) é feito no cliente a
// partir de `data.agenda` — o backend já traz até 200 agendamentos do
// período inteiro (ver DashboardController::professionalDashboardData()),
// então não há necessidade de outra ida ao servidor só para contar por dia.
type AgendaEntry = ProfessionalDashboardData['agenda'][number];

const appointmentsByDay = computed(() => {
    const map = new Map<string, AgendaEntry[]>();

    for (const appointment of props.data.agenda) {
        const day = appointment.starts_at.slice(0, 10);
        const list = map.get(day) ?? [];
        list.push(appointment);
        map.set(day, list);
    }

    return map;
});

type MonthCell = { date: string; day: number; count: number } | null;

const monthCells = computed<MonthCell[]>(() => {
    const [year, month] = props.data.referenceDate
        .slice(0, 7)
        .split('-')
        .map(Number);
    const daysInMonth = new Date(year, month, 0).getDate();
    const leadingBlanks = new Date(year, month - 1, 1).getDay();

    const blanks: MonthCell[] = Array.from(
        { length: leadingBlanks },
        () => null,
    );
    const days: MonthCell[] = Array.from(
        { length: daysInMonth },
        (_, index) => {
            const day = index + 1;
            const date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

            return {
                date,
                day,
                count: appointmentsByDay.value.get(date)?.length ?? 0,
            };
        },
    );

    return blanks.concat(days);
});

const selectedCalendarDay = ref<string | null>(null);

watch(
    () => `${props.data.period}|${props.data.referenceDate}`,
    () => {
        selectedCalendarDay.value = null;
    },
);

function selectCalendarDay(date: string) {
    selectedCalendarDay.value =
        selectedCalendarDay.value === date ? null : date;
}

const visibleAgenda = computed(() => {
    if (props.data.period === 'month' && selectedCalendarDay.value) {
        return appointmentsByDay.value.get(selectedCalendarDay.value) ?? [];
    }

    return props.data.agenda;
});

function formatSelectedCalendarDay(): string {
    if (!selectedCalendarDay.value) {
        return '';
    }

    return formatDate(selectedCalendarDay.value);
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
    alarm_at: '',
});

function submitReminder() {
    reminderForm
        .transform((data) => ({
            ...data,
            // `alarm_at` do input é hora local do navegador
            // (`datetime-local`, sem fuso) — convertida aqui para UTC antes
            // de enviar, já que o servidor só guarda o instante absoluto. O
            // alarme em si só é conferido no cliente (ver checkAlarms()),
            // nunca dispara nada no servidor.
            alarm_at: data.alarm_at
                ? new Date(data.alarm_at).toISOString()
                : null,
        }))
        .post(storeReminder().url, {
            preserveScroll: true,
            onSuccess: () => reminderForm.reset(),
        });
}

function removeReminder(reminder: Reminder) {
    router.delete(destroyReminder(reminder.id).url, { preserveScroll: true });
}

function formatAlarmTime(value: string): string {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

const expandedReminder = ref<Reminder | null>(null);

function expandReminder(reminder: Reminder) {
    expandedReminder.value = reminder;
}

function removeExpandedReminder() {
    if (!expandedReminder.value) {
        return;
    }

    removeReminder(expandedReminder.value);
    expandedReminder.value = null;
}

// Alarme de post-it (ex.: "tomar remédio às 12h") — conferido só no cliente,
// enquanto o dashboard estiver aberto (sem push/Service Worker nesta fase).
// Mostra só um alerta por vez: enquanto um estiver aberto, os demais
// aguardam a próxima checagem depois que este for silenciado.
const firedAlarmReminder = ref<Reminder | null>(null);
let alarmCheckIntervalId: ReturnType<typeof setInterval> | undefined;

function checkAlarms() {
    if (firedAlarmReminder.value) {
        return;
    }

    const now = Date.now();
    const due = props.data.reminders.find(
        (reminder) =>
            reminder.alarm_at !== null &&
            new Date(reminder.alarm_at).getTime() <= now,
    );

    if (due) {
        firedAlarmReminder.value = due;
    }
}

onMounted(() => {
    checkAlarms();
    alarmCheckIntervalId = setInterval(checkAlarms, 15000);
});

onBeforeUnmount(() => {
    if (alarmCheckIntervalId) {
        clearInterval(alarmCheckIntervalId);
    }
});

function dismissFiredAlarm() {
    if (!firedAlarmReminder.value) {
        return;
    }

    router.patch(
        dismissReminderAlarm(firedAlarmReminder.value.id).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                firedAlarmReminder.value = null;
            },
        },
    );
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

        <div
            v-if="data.completedWithoutMedicalRecordCount > 0"
            class="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30"
            role="status"
        >
            <AlertTriangle
                class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400"
            />
            <p class="font-medium text-amber-900 dark:text-amber-200">
                {{ data.completedWithoutMedicalRecordCount }}
                {{
                    data.completedWithoutMedicalRecordCount === 1
                        ? 'atendimento concluído sem prontuário registrado ainda'
                        : 'atendimentos concluídos sem prontuário registrado ainda'
                }}
            </p>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <Card
                role="button"
                tabindex="0"
                data-testid="dashboard-counter-card"
                class="hover:bg-muted/50 cursor-pointer transition-colors"
                @click="goToAgenda()"
                @keydown.enter="goToAgenda()"
            >
                <CardHeader class="pb-2">
                    <CardDescription>Em aberto</CardDescription>
                    <CardTitle class="text-3xl">{{
                        data.counters.open
                    }}</CardTitle>
                </CardHeader>
            </Card>
            <Card
                role="button"
                tabindex="0"
                data-testid="dashboard-counter-card"
                class="hover:bg-muted/50 cursor-pointer transition-colors"
                @click="goToAgenda()"
                @keydown.enter="goToAgenda()"
            >
                <CardHeader class="pb-2">
                    <CardDescription>Agendados</CardDescription>
                    <CardTitle class="text-3xl">{{
                        data.counters.scheduled
                    }}</CardTitle>
                </CardHeader>
            </Card>
            <Card
                role="button"
                tabindex="0"
                data-testid="dashboard-counter-card"
                class="hover:bg-muted/50 cursor-pointer transition-colors"
                @click="goToAgenda()"
                @keydown.enter="goToAgenda()"
            >
                <CardHeader class="pb-2">
                    <CardDescription>Executados</CardDescription>
                    <CardTitle class="text-3xl">{{
                        data.counters.completed
                    }}</CardTitle>
                </CardHeader>
            </Card>
            <Link :href="myAppointmentRequests()" class="block">
                <Card
                    class="hover:bg-muted/50 cursor-pointer transition-colors"
                >
                    <CardHeader class="pb-2">
                        <CardDescription>Pré-agendamentos</CardDescription>
                        <CardTitle class="text-3xl">{{
                            data.pendingAppointmentRequestsCount
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <span class="text-primary text-sm font-medium">
                            Ver pré-agendamentos
                        </span>
                    </CardContent>
                </Card>
            </Link>
        </div>

        <div class="grid gap-4 lg:grid-cols-3 lg:items-start">
            <Card id="agenda" class="lg:col-span-2">
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
                            <Input
                                :model-value="data.referenceDate"
                                type="date"
                                aria-label="Ir para uma data específica"
                                class="w-auto"
                                @update:model-value="
                                    (value) => goToDate(String(value))
                                "
                            />
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div v-if="data.period === 'month'" class="grid gap-2">
                        <div class="grid grid-cols-7 gap-1 text-center text-sm">
                            <span
                                v-for="label in [
                                    'D',
                                    'S',
                                    'T',
                                    'Q',
                                    'Q',
                                    'S',
                                    'S',
                                ]"
                                :key="label"
                                class="text-muted-foreground text-xs font-medium"
                                >{{ label }}</span
                            >
                            <template
                                v-for="(cell, index) in monthCells"
                                :key="index"
                            >
                                <span v-if="cell === null"></span>
                                <button
                                    v-else
                                    type="button"
                                    :aria-label="
                                        cell.count > 0
                                            ? `Dia ${cell.day}, ${cell.count} agendamento(s)`
                                            : `Dia ${cell.day}, sem agendamentos`
                                    "
                                    :aria-pressed="
                                        selectedCalendarDay === cell.date
                                    "
                                    class="flex flex-col items-center gap-0.5 rounded-md py-1.5 text-sm"
                                    :class="[
                                        cell.count > 0
                                            ? 'bg-primary/10 hover:bg-primary/20 cursor-pointer'
                                            : 'text-muted-foreground/50',
                                        selectedCalendarDay === cell.date &&
                                            'bg-primary text-primary-foreground hover:bg-primary',
                                    ]"
                                    @click="selectCalendarDay(cell.date)"
                                >
                                    {{ cell.day }}
                                    <span
                                        v-if="cell.count > 0"
                                        class="size-1.5 rounded-full bg-current"
                                    ></span>
                                </button>
                            </template>
                        </div>
                        <div
                            v-if="selectedCalendarDay"
                            class="flex items-center justify-between text-sm"
                        >
                            <p class="flex items-center gap-2 font-medium">
                                <CalendarDays class="size-4" />
                                Agendamentos em
                                {{ formatSelectedCalendarDay() }}
                            </p>
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground underline underline-offset-2"
                                @click="selectedCalendarDay = null"
                            >
                                Ver mês inteiro
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="visibleAgenda.length === 0"
                        class="text-muted-foreground py-6 text-center text-sm"
                    >
                        Nenhum agendamento neste período.
                    </p>
                    <ul v-else class="grid gap-2">
                        <li
                            v-for="appointment in visibleAgenda"
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
                                <Badge
                                    :variant="statusVariant(appointment.status)"
                                >
                                    {{ appointment.status_label }}
                                </Badge>
                                <Button
                                    v-if="appointment.status === 'requested'"
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="
                                        runAppointmentAction(
                                            confirmAppointment(appointment.id)
                                                .url,
                                        )
                                    "
                                    >Confirmar</Button
                                >
                                <template
                                    v-else-if="
                                        appointment.status === 'confirmed'
                                    "
                                >
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        @click="
                                            runAppointmentAction(
                                                checkInAppointment(
                                                    appointment.id,
                                                ).url,
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
                                                noShowAppointment(
                                                    appointment.id,
                                                ).url,
                                            )
                                        "
                                        >Não compareceu</Button
                                    >
                                </template>
                                <Button
                                    v-else-if="
                                        appointment.status === 'checked_in'
                                    "
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="
                                        runAppointmentAction(
                                            startAppointment(appointment.id)
                                                .url,
                                        )
                                    "
                                    >Iniciar atendimento</Button
                                >
                                <Button
                                    v-else-if="
                                        appointment.status === 'in_progress'
                                    "
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="
                                        runAppointmentAction(
                                            completeAppointment(appointment.id)
                                                .url,
                                        )
                                    "
                                    >Concluir</Button
                                >
                                <Link :href="showMedicalRecord(appointment.id)">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                    >
                                        {{
                                            appointment.medical_record_id
                                                ? 'Prontuário'
                                                : 'Abrir prontuário'
                                        }}
                                    </Button>
                                </Link>
                            </div>
                        </li>
                    </ul>
                    <p
                        v-if="data.agendaTruncated"
                        class="text-muted-foreground mt-2 text-xs"
                    >
                        Mostrando os primeiros agendamentos do período — reduza
                        o período para ver a lista completa.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Avisos e lembretes</CardTitle>
                    <CardDescription
                        >Anotações pessoais, visíveis só para
                        você.</CardDescription
                    >
                </CardHeader>
                <CardContent class="grid gap-4">
                    <p
                        v-if="data.reminders.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        Nenhum lembrete ainda.
                    </p>
                    <div v-else class="grid grid-cols-2 gap-3">
                        <button
                            v-for="reminder in data.reminders"
                            :key="reminder.id"
                            type="button"
                            :class="[
                                'relative rounded-md border p-3 text-left text-sm shadow-sm',
                                REMINDER_COLORS[reminder.color],
                            ]"
                            @click="expandReminder(reminder)"
                        >
                            <span
                                role="button"
                                tabindex="-1"
                                class="text-muted-foreground hover:text-foreground absolute right-1 top-1 rounded p-1"
                                aria-label="Remover lembrete"
                                @click.stop="removeReminder(reminder)"
                            >
                                <X class="size-3.5" />
                            </span>
                            <AlarmClock
                                v-if="reminder.alarm_at"
                                class="text-muted-foreground mb-1 size-3.5"
                            />
                            <p class="line-clamp-3 whitespace-pre-wrap pr-4">
                                {{ reminder.body }}
                            </p>
                        </button>
                    </div>

                    <form
                        class="flex flex-col gap-2"
                        @submit.prevent="submitReminder"
                    >
                        <Textarea
                            v-model="reminderForm.body"
                            placeholder="Escreva um lembrete..."
                            rows="2"
                            maxlength="280"
                        />
                        <Input
                            v-model="reminderForm.alarm_at"
                            type="datetime-local"
                            aria-label="Alarme (opcional)"
                        />
                        <div class="flex items-center gap-2">
                            <Select v-model="reminderForm.color">
                                <SelectTrigger class="w-28">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="yellow"
                                        >Amarelo</SelectItem
                                    >
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
                </CardContent>
            </Card>
        </div>

        <Dialog
            :open="expandedReminder !== null"
            @update:open="(open) => !open && (expandedReminder = null)"
        >
            <DialogContent v-if="expandedReminder">
                <DialogHeader>
                    <DialogTitle>Lembrete</DialogTitle>
                    <DialogDescription v-if="expandedReminder.alarm_at">
                        Alarme para
                        {{ formatAlarmTime(expandedReminder.alarm_at) }}
                    </DialogDescription>
                    <DialogDescription v-else>
                        Anotação pessoal, visível só para você.
                    </DialogDescription>
                </DialogHeader>
                <p
                    :class="[
                        'whitespace-pre-wrap rounded-md border p-3 text-sm',
                        REMINDER_COLORS[expandedReminder.color],
                    ]"
                >
                    {{ expandedReminder.body }}
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="expandedReminder = null"
                        >Fechar</Button
                    >
                    <Button
                        variant="destructive"
                        @click="removeExpandedReminder"
                        >Remover</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="firedAlarmReminder !== null">
            <DialogContent
                v-if="firedAlarmReminder"
                :show-close-button="false"
                @escape-key-down.prevent
                @pointer-down-outside.prevent
            >
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <AlarmClock class="size-5" />
                        Alarme
                    </DialogTitle>
                    <DialogDescription>
                        {{ formatAlarmTime(firedAlarmReminder.alarm_at ?? '') }}
                    </DialogDescription>
                </DialogHeader>
                <p
                    :class="[
                        'whitespace-pre-wrap rounded-md border p-3 text-sm',
                        REMINDER_COLORS[firedAlarmReminder.color],
                    ]"
                >
                    {{ firedAlarmReminder.body }}
                </p>
                <DialogFooter>
                    <Button @click="dismissFiredAlarm">Fechar alarme</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

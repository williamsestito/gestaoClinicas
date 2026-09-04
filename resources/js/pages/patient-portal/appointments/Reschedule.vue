<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Clock3, Loader2 } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useAvailabilityCalendarGrid } from '@/composables/useAvailabilityCalendarGrid';
import type {
    AvailabilityDate,
    AvailabilityTime,
} from '@/composables/usePatientAvailabilitySearch';
import patientPortal from '@/routes/patient-portal';
import availability from '@/routes/patient-portal/appointments/availability';

type AppointmentInfo = {
    id: string;
    starts_at: string;
    unit_id: string;
    professional_id: string;
    service_id: string;
    professional_name: string;
    service_name: string;
    duration_minutes: number;
};

const props = defineProps<{
    patient: { id: string };
    appointment: AppointmentInfo;
}>();

defineOptions({
    layout: {
        title: 'Reagendar',
    },
});

const form = useForm({
    starts_at: '',
});

// Profissional, serviço e unidade permanecem os mesmos do agendamento
// original — reagendar só troca a data/horário, nunca esses vínculos.
const dates = ref<AvailabilityDate[]>([]);
const times = ref<AvailabilityTime[]>([]);
const selectedDate = ref<string>(props.appointment.starts_at.slice(0, 10));
const currentMonth = ref(props.appointment.starts_at.slice(0, 7));
const loadingKeys = ref<Set<'dates' | 'times'>>(new Set());
const error = ref<string | null>(null);

function isLoading(key: 'dates' | 'times'): boolean {
    return loadingKeys.value.has(key);
}

async function fetchJson<T>(url: string): Promise<T[]> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error('request-failed');
    }

    const body = (await response.json()) as { data: T[] };

    return body.data;
}

async function loadDates() {
    loadingKeys.value.add('dates');
    error.value = null;

    try {
        dates.value = await fetchJson<AvailabilityDate>(
            availability.dates({
                query: {
                    unit_id: props.appointment.unit_id,
                    service_id: props.appointment.service_id,
                    professional_id: props.appointment.professional_id,
                    month: currentMonth.value,
                },
            }).url,
        );
    } catch {
        error.value =
            'Não foi possível carregar o calendário. Tente novamente.';
    } finally {
        loadingKeys.value.delete('dates');
    }
}

async function loadTimes() {
    loadingKeys.value.add('times');
    error.value = null;

    try {
        times.value = await fetchJson<AvailabilityTime>(
            availability.times({
                query: {
                    unit_id: props.appointment.unit_id,
                    service_id: props.appointment.service_id,
                    professional_id: props.appointment.professional_id,
                    date: selectedDate.value,
                },
            }).url,
        );
    } catch {
        error.value = 'Não foi possível carregar os horários. Tente novamente.';
    } finally {
        loadingKeys.value.delete('times');
    }
}

onMounted(() => {
    void loadDates();
    void loadTimes();
});

const { monthLabel, calendarCells, shiftMonth } = useAvailabilityCalendarGrid(
    dates,
    currentMonth,
);

function goToPreviousMonth() {
    currentMonth.value = shiftMonth(-1);
    void loadDates();
}

function goToNextMonth() {
    currentMonth.value = shiftMonth(1);
    void loadDates();
}

function selectDate(date: string) {
    selectedDate.value = date;
    form.starts_at = '';
    void loadTimes();
}

function formatSelectedDate(): string {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: 'long',
        weekday: 'long',
    }).format(new Date(`${selectedDate.value}T00:00:00`));
}

function selectTime(time: string) {
    form.starts_at = `${selectedDate.value}T${time}:00`;
}

function cancel() {
    router.get(patientPortal.appointments.index(props.patient.id).url);
}

function submit() {
    form.put(
        patientPortal.appointments.reschedule.update([
            props.patient.id,
            props.appointment.id,
        ]).url,
    );
}
</script>

<template>
    <Head title="Reagendar" />

    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-xl font-medium">Reagendar</h1>
            <p class="text-muted-foreground text-sm">
                {{ appointment.service_name }} com
                {{ appointment.professional_name }}.
            </p>
        </div>

        <form class="flex max-w-2xl flex-col gap-6" @submit.prevent="submit">
            <div
                class="border-border bg-card grid gap-4 rounded-2xl border p-6 shadow-sm"
            >
                <div class="grid gap-3">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-2 text-sm font-medium">
                            <CalendarDays class="size-4" />
                            Selecione uma data
                        </p>
                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="goToPreviousMonth"
                                >Anterior</Button
                            >
                            <span
                                class="min-w-32 text-center text-sm capitalize"
                                >{{ monthLabel }}</span
                            >
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="goToNextMonth"
                                >Próximo</Button
                            >
                        </div>
                    </div>

                    <div
                        v-if="isLoading('dates')"
                        class="text-muted-foreground flex items-center gap-2 text-sm"
                        aria-live="polite"
                    >
                        <Loader2 class="size-4 animate-spin" />
                        Carregando calendário…
                    </div>

                    <div
                        v-else
                        class="grid grid-cols-7 gap-1 text-center text-sm"
                    >
                        <span
                            v-for="label in ['D', 'S', 'T', 'Q', 'Q', 'S', 'S']"
                            :key="label"
                            class="text-muted-foreground text-xs font-medium"
                            >{{ label }}</span
                        >
                        <template
                            v-for="(cell, index) in calendarCells"
                            :key="index"
                        >
                            <span v-if="cell === null"></span>
                            <button
                                v-else
                                type="button"
                                :disabled="!cell.isAvailable"
                                :aria-label="
                                    cell.isAvailable
                                        ? `Dia ${cell.day}, disponível`
                                        : `Dia ${cell.day}, indisponível`
                                "
                                :aria-pressed="selectedDate === cell.date"
                                class="rounded-md py-1.5 text-sm"
                                :class="[
                                    cell.isAvailable
                                        ? 'bg-primary/10 hover:bg-primary/20 cursor-pointer'
                                        : 'text-muted-foreground/50 cursor-not-allowed',
                                    selectedDate === cell.date &&
                                        'bg-primary text-primary-foreground hover:bg-primary',
                                ]"
                                @click="
                                    cell.isAvailable && selectDate(cell.date)
                                "
                            >
                                {{ cell.day }}
                            </button>
                        </template>
                    </div>
                </div>

                <div class="grid gap-3">
                    <p class="flex items-center gap-2 text-sm font-medium">
                        <Clock3 class="size-4" />
                        Horários em {{ formatSelectedDate() }}
                    </p>

                    <div
                        v-if="isLoading('times')"
                        class="text-muted-foreground flex items-center gap-2 text-sm"
                        aria-live="polite"
                    >
                        <Loader2 class="size-4 animate-spin" />
                        Carregando horários…
                    </div>

                    <p
                        v-else-if="times.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        Nenhum horário disponível para os filtros selecionados.
                    </p>

                    <div v-else class="flex flex-wrap gap-2">
                        <Button
                            v-for="slot in times"
                            :key="slot.time"
                            type="button"
                            :variant="
                                form.starts_at ===
                                `${selectedDate}T${slot.time}:00`
                                    ? 'default'
                                    : 'outline'
                            "
                            size="sm"
                            @click="selectTime(slot.time)"
                        >
                            {{ slot.time }}
                        </Button>
                    </div>
                </div>

                <p v-if="error" role="alert" class="text-destructive text-sm">
                    {{ error }}
                </p>
            </div>

            <div class="flex items-center justify-end gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    :disabled="form.processing"
                    @click="cancel"
                >
                    Voltar
                </Button>
                <Button
                    type="submit"
                    :disabled="form.processing || !form.starts_at"
                >
                    Confirmar novo horário
                </Button>
            </div>
        </form>
    </div>
</template>

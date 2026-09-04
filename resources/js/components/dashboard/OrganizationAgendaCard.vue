<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import { index as appointmentsIndex } from '@/routes/settings/appointments';

export type OrgAgendaData = {
    date: string;
    professionalId: string | null;
    professionals: { id: string; display_name: string }[];
    appointments: {
        id: string;
        starts_at: string;
        ends_at: string;
        status: string;
        status_label: string;
        professional_name: string;
        patient_name: string;
        service_name: string;
        unit_name: string;
    }[];
};

const props = defineProps<{
    data: OrgAgendaData;
}>();

/**
 * Recarga parcial (mesmo padrão de ProfessionalDashboard.vue::reload()) —
 * troca de dia/profissional nunca navega para fora do dashboard, só
 * atualiza `orgAgenda` via Inertia partial reload.
 */
function reload(params: { date?: string; professionalId?: string | null }) {
    const professionalId =
        params.professionalId !== undefined
            ? params.professionalId
            : props.data.professionalId;

    router.get(
        dashboard().url,
        {
            agenda_date: params.date ?? props.data.date,
            agenda_professional_id: professionalId || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['orgAgenda'],
        },
    );
}

function shiftDate(direction: 1 | -1): string {
    const current = new Date(`${props.data.date}T00:00:00`);
    current.setDate(current.getDate() + direction);

    return current.toISOString().slice(0, 10);
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

function goToDate(value: string) {
    reload({ date: value });
}

function changeProfessional(event: Event) {
    reload({ professionalId: (event.target as HTMLSelectElement).value });
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString('pt-BR', {
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

    return 'default';
}
</script>

<template>
    <Card>
        <CardHeader
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <CardTitle>Agenda</CardTitle>
                <CardDescription
                    >Agendamentos da clínica, por profissional.</CardDescription
                >
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select
                    :value="data.professionalId ?? ''"
                    aria-label="Filtrar por profissional"
                    class="border-input shadow-xs focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 py-1 text-sm outline-none focus-visible:ring-[3px]"
                    @change="changeProfessional"
                >
                    <option value="">Todos os profissionais</option>
                    <option
                        v-for="professional in data.professionals"
                        :key="professional.id"
                        :value="professional.id"
                    >
                        {{ professional.display_name }}
                    </option>
                </select>
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
                        :model-value="data.date"
                        type="date"
                        aria-label="Ir para uma data específica"
                        class="w-auto"
                        @update:model-value="(value) => goToDate(String(value))"
                    />
                </div>
            </div>
        </CardHeader>
        <CardContent>
            <p
                v-if="data.appointments.length === 0"
                class="text-muted-foreground py-6 text-center text-sm"
            >
                Nenhum agendamento neste dia.
            </p>

            <ul v-else class="grid gap-2">
                <li
                    v-for="appointment in data.appointments"
                    :key="appointment.id"
                    class="flex flex-col gap-1 rounded-md border p-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="space-y-0.5">
                        <p class="font-medium">
                            {{ formatTime(appointment.starts_at) }}–{{
                                formatTime(appointment.ends_at)
                            }}
                            — {{ appointment.patient_name }}
                        </p>
                        <p class="text-muted-foreground text-sm">
                            {{ appointment.professional_name }}
                            <template v-if="appointment.service_name">
                                · {{ appointment.service_name }}</template
                            >
                        </p>
                    </div>
                    <Badge :variant="statusVariant(appointment.status)">
                        {{ appointment.status_label }}
                    </Badge>
                </li>
            </ul>

            <Link
                :href="appointmentsIndex().url"
                class="text-primary mt-3 inline-block text-sm font-medium underline-offset-4 hover:underline"
            >
                Ver agenda completa
            </Link>
        </CardContent>
    </Card>
</template>

<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { formatDateTimeBr } from '@/lib/masks';

export type OperationalSummary = {
    is_operational: boolean;
    status: 'operational' | 'incomplete' | 'inactive';
    status_label: string;
    reasons: string[];
    warnings: string[];
    primary_unit: { id: string; name: string } | null;
    active_units_count: number;
    primary_specialty: { id: string; name: string } | null;
    specialties_count: number;
    primary_registration: {
        council: string;
        masked_number: string;
        validity_status: string;
    } | null;
    active_services_count: number;
    has_working_hours: boolean;
    next_time_block: { type: string; starts_at: string } | null;
};

const TIME_BLOCK_TYPE_LABELS: Record<string, string> = {
    vacation: 'Férias',
    day_off: 'Folga',
    absence: 'Ausência',
    administrative_block: 'Bloqueio administrativo',
    external_event: 'Evento externo',
    partial_unavailability: 'Indisponibilidade parcial',
};

const props = defineProps<{
    summary: OperationalSummary;
}>();

const statusClasses = computed(() => {
    if (props.summary.status === 'operational') {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200';
    }

    if (props.summary.status === 'incomplete') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200';
    }

    return 'bg-muted text-muted-foreground';
});

function formatDate(value: string): string {
    return formatDateTimeBr(value, { withTime: false });
}
</script>

<template>
    <Card>
        <CardContent class="grid gap-4 py-4">
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="statusClasses"
                >
                    {{ summary.status_label }}
                </span>
                <span
                    v-for="reason in summary.reasons"
                    :key="reason"
                    class="text-xs text-muted-foreground"
                >
                    {{ reason }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                <div>
                    <p class="text-xs text-muted-foreground">
                        Unidade principal
                    </p>
                    <p>{{ summary.primary_unit?.name ?? 'Não definida' }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ summary.active_units_count }} unidade(s) ativa(s)
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        Especialidade principal
                    </p>
                    <p>
                        {{ summary.primary_specialty?.name ?? 'Não definida' }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ summary.specialties_count }} especialidade(s)
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        Registro principal
                    </p>
                    <p v-if="summary.primary_registration">
                        {{ summary.primary_registration.council }}
                        {{ summary.primary_registration.masked_number }}
                    </p>
                    <p v-else>Não definido</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Serviços ativos</p>
                    <p>{{ summary.active_services_count }}</p>
                    <p class="text-xs text-muted-foreground">
                        Jornada
                        {{
                            summary.has_working_hours
                                ? 'configurada'
                                : 'não configurada'
                        }}
                    </p>
                </div>
            </div>

            <p
                v-if="summary.next_time_block"
                class="text-sm text-muted-foreground"
            >
                Próxima ausência:
                {{
                    TIME_BLOCK_TYPE_LABELS[summary.next_time_block.type] ??
                    summary.next_time_block.type
                }}
                em {{ formatDate(summary.next_time_block.starts_at) }}
            </p>

            <ul v-if="summary.warnings.length > 0" class="grid gap-1">
                <li
                    v-for="warning in summary.warnings"
                    :key="warning"
                    class="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-400"
                >
                    <AlertTriangle class="size-4 shrink-0" />
                    {{ warning }}
                </li>
            </ul>
        </CardContent>
    </Card>
</template>

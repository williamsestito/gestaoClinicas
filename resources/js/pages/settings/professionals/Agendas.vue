<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Input } from '@/components/ui/input';
import { formatDateBr } from '@/lib/masks';
import { dashboard } from '@/routes';
import { index as indexProfessionals } from '@/routes/settings/professionals';
import { index as professionalAvailability } from '@/routes/settings/professionals/availability';

export type AgendaRow = {
    id: string;
    display_name: string;
    status: 'active' | 'inactive';
    operational_status: 'operational' | 'incomplete' | 'inactive';
    primary_specialty_name: string | null;
    unit_ids: string[];
    unit_names: string[];
    specialty_ids: string[];
    service_ids: string[];
    weekdays: number[];
    vigency_from: string | null;
    vigency_until: string | null;
    working_hours_count: number;
    has_working_hours: boolean;
    ongoing_time_blocks_count: number;
    has_ongoing_absence: boolean;
    has_conflict_alert: boolean;
};

type FilterOption = { id: string; name: string };

const WEEKDAY_LABELS: Record<number, string> = {
    0: 'Dom',
    1: 'Seg',
    2: 'Ter',
    3: 'Qua',
    4: 'Qui',
    5: 'Sex',
    6: 'Sáb',
};

const OPERATIONAL_STATUS_LABELS: Record<
    AgendaRow['operational_status'],
    string
> = {
    operational: 'Operacional',
    incomplete: 'Configuração incompleta',
    inactive: 'Inativo',
};

const props = defineProps<{
    professionals: AgendaRow[];
    units?: FilterOption[];
    specialties?: FilterOption[];
    services?: FilterOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e serviços' },
            { title: 'Profissionais', href: indexProfessionals() },
            { title: 'Agendas' },
        ],
    },
});

const search = ref('');
const unitFilter = ref('all');
const specialtyFilter = ref('all');
const serviceFilter = ref('all');
const statusFilter = ref<'all' | 'active' | 'inactive'>('all');
const workingHoursFilter = ref<'all' | 'with' | 'without'>('all');
const ongoingAbsenceFilter = ref<'all' | 'with' | 'without'>('all');
const periodFrom = ref('');
const periodUntil = ref('');

function weekdayLabels(weekdays: number[]): string {
    if (weekdays.length === 0) {
        return '—';
    }

    return weekdays.map((day) => WEEKDAY_LABELS[day]).join(', ');
}

function formatVigency(row: AgendaRow): string {
    if (!row.vigency_from && !row.vigency_until) {
        return row.working_hours_count > 0 ? 'Sem limite definido' : '—';
    }

    const from = row.vigency_from
        ? formatDateBr(row.vigency_from)
        : 'início em aberto';
    const until = row.vigency_until
        ? formatDateBr(row.vigency_until)
        : 'sem data final';

    return `${from} até ${until}`;
}

function matchesPeriod(row: AgendaRow): boolean {
    if (!periodFrom.value && !periodUntil.value) {
        return true;
    }

    if (row.working_hours_count === 0) {
        return false;
    }

    const rowFrom = row.vigency_from ?? '0000-01-01';
    const rowUntil = row.vigency_until ?? '9999-12-31';
    const filterFrom = periodFrom.value || '0000-01-01';
    const filterUntil = periodUntil.value || '9999-12-31';

    return rowFrom <= filterUntil && rowUntil >= filterFrom;
}

const filteredProfessionals = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.professionals.filter((row) => {
        const matchesSearch =
            term === '' || row.display_name.toLowerCase().includes(term);

        const matchesUnit =
            unitFilter.value === 'all' ||
            row.unit_ids.includes(unitFilter.value);

        const matchesSpecialty =
            specialtyFilter.value === 'all' ||
            row.specialty_ids.includes(specialtyFilter.value);

        const matchesService =
            serviceFilter.value === 'all' ||
            row.service_ids.includes(serviceFilter.value);

        const matchesStatus =
            statusFilter.value === 'all' || row.status === statusFilter.value;

        const matchesWorkingHours =
            workingHoursFilter.value === 'all' ||
            (workingHoursFilter.value === 'with'
                ? row.has_working_hours
                : !row.has_working_hours);

        const matchesAbsence =
            ongoingAbsenceFilter.value === 'all' ||
            (ongoingAbsenceFilter.value === 'with'
                ? row.has_ongoing_absence
                : !row.has_ongoing_absence);

        return (
            matchesSearch &&
            matchesUnit &&
            matchesSpecialty &&
            matchesService &&
            matchesStatus &&
            matchesWorkingHours &&
            matchesAbsence &&
            matchesPeriod(row)
        );
    });
});

const hasAny = computed(() => props.professionals.length > 0);
const hasFilteredResults = computed(
    () => filteredProfessionals.value.length > 0,
);

function goToDetails(row: AgendaRow) {
    router.get(professionalAvailability(row.id).url);
}
</script>

<template>
    <Head title="Agendas dos profissionais" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Agendas"
            description="Visão consolidada, somente leitura, da jornada e das ausências de cada profissional. Alterações continuam sendo feitas na ficha de cada profissional."
        />

        <div
            v-if="hasAny"
            class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center"
        >
            <Input
                v-model="search"
                placeholder="Buscar por nome"
                aria-label="Buscar profissionais por nome"
                class="sm:max-w-xs"
            />

            <select
                v-model="unitFilter"
                aria-label="Filtrar agendas por unidade"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Todas as unidades</option>
                <option
                    v-for="unit in units ?? []"
                    :key="unit.id"
                    :value="unit.id"
                >
                    {{ unit.name }}
                </option>
            </select>

            <select
                v-model="specialtyFilter"
                aria-label="Filtrar agendas por especialidade"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Todas as especialidades</option>
                <option
                    v-for="specialty in specialties ?? []"
                    :key="specialty.id"
                    :value="specialty.id"
                >
                    {{ specialty.name }}
                </option>
            </select>

            <select
                v-model="serviceFilter"
                aria-label="Filtrar agendas por serviço"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Todos os serviços</option>
                <option
                    v-for="service in services ?? []"
                    :key="service.id"
                    :value="service.id"
                >
                    {{ service.name }}
                </option>
            </select>

            <select
                v-model="statusFilter"
                aria-label="Filtrar agendas por status do profissional"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Status: todos</option>
                <option value="active">Ativos</option>
                <option value="inactive">Inativos</option>
            </select>

            <select
                v-model="workingHoursFilter"
                aria-label="Filtrar agendas por jornada"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Com ou sem jornada</option>
                <option value="with">Com jornada</option>
                <option value="without">Sem jornada</option>
            </select>

            <select
                v-model="ongoingAbsenceFilter"
                aria-label="Filtrar agendas por bloqueio em andamento"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Com ou sem bloqueio</option>
                <option value="with">Com bloqueio em andamento</option>
                <option value="without">Sem bloqueio em andamento</option>
            </select>

            <div class="flex items-center gap-2 text-sm">
                <label for="period-from" class="text-muted-foreground"
                    >Período</label
                >
                <Input
                    id="period-from"
                    v-model="periodFrom"
                    type="date"
                    aria-label="Filtrar agendas por data inicial do período"
                    class="w-auto"
                />
                <span class="text-muted-foreground">até</span>
                <Input
                    v-model="periodUntil"
                    type="date"
                    aria-label="Filtrar agendas por data final do período"
                    class="w-auto"
                />
            </div>
        </div>

        <EmptyState
            v-if="!hasAny"
            title="Nenhum profissional ativo cadastrado ainda."
        />
        <EmptyState
            v-else-if="!hasFilteredResults"
            title="Nenhuma agenda corresponde aos filtros informados."
        />

        <div v-else class="overflow-x-auto rounded-md border">
            <table class="w-full text-sm">
                <thead
                    class="border-b bg-muted/50 text-left text-muted-foreground"
                >
                    <tr>
                        <th class="px-4 py-2 font-medium">Profissional</th>
                        <th class="px-4 py-2 font-medium">
                            Especialidade principal
                        </th>
                        <th class="px-4 py-2 font-medium">Unidades</th>
                        <th class="px-4 py-2 font-medium">
                            Dias de atendimento
                        </th>
                        <th class="px-4 py-2 font-medium">Vigência</th>
                        <th class="px-4 py-2 font-medium">Bloqueios ativos</th>
                        <th class="px-4 py-2 font-medium">
                            Situação operacional
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in filteredProfessionals"
                        :key="row.id"
                        class="cursor-pointer border-b last:border-0 hover:bg-muted/30"
                        @click="goToDetails(row)"
                    >
                        <td class="px-4 py-3 font-medium">
                            {{ row.display_name }}
                            <span
                                v-if="row.has_conflict_alert"
                                class="ml-1 text-xs text-amber-600 dark:text-amber-400"
                                title="Possui unidade vinculada, mas nenhuma jornada configurada."
                            >
                                ⚠ sem jornada
                            </span>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ row.primary_specialty_name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ row.unit_names.join(', ') || '—' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ weekdayLabels(row.weekdays) }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ formatVigency(row) }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ row.ongoing_time_blocks_count }}
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge
                                :status="
                                    row.operational_status === 'operational'
                                        ? 'active'
                                        : 'inactive'
                                "
                                :deleted-at="null"
                            />
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{
                                    OPERATIONAL_STATUS_LABELS[
                                        row.operational_status
                                    ]
                                }}
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="hasAny" class="text-sm text-muted-foreground">
            {{ filteredProfessionals.length }} de {{ professionals.length }}
            agendas
        </p>
    </div>
</template>

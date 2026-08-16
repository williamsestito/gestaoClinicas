<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarClock, Plus, Search, UserRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import ProfessionalForm from '@/components/professionals/ProfessionalForm.vue';
import type { EligibleUser } from '@/components/professionals/ProfessionalForm.vue';
import ProfessionalRowActions from '@/components/professionals/ProfessionalRowActions.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDateTimeBr } from '@/lib/masks';
import { dashboard } from '@/routes';
import {
    activate,
    agendas,
    deactivate,
    destroy,
    edit as editRoute,
    restore as restoreRoute,
} from '@/routes/settings/professionals';

export type ProfessionalRow = {
    id: string;
    display_name: string;
    email: string | null;
    phone: string | null;
    document: string | null;
    photo_url: string | null;
    status: 'active' | 'inactive';
    linked_user_name: string | null;
    deleted_at: string | null;
    updated_at: string;
    unit_ids: string[];
    unit_names: string[];
    specialty_ids: string[];
    specialty_names: string[];
    service_ids: string[];
    active_services_count: number;
    has_active_unit: boolean;
    has_active_specialty: boolean;
    has_active_service: boolean;
    has_working_hours: boolean;
    has_ongoing_absence: boolean;
    operational_status: 'operational' | 'incomplete' | 'inactive';
};

export type FilterOption = { id: string; name: string };

const OPERATIONAL_STATUS_LABELS: Record<
    ProfessionalRow['operational_status'],
    string
> = {
    operational: 'Operacional',
    incomplete: 'Configuração incompleta',
    inactive: 'Inativo',
};

const props = defineProps<{
    professionals: ProfessionalRow[];
    eligibleUsers?: EligibleUser[];
    units?: FilterOption[];
    specialties?: FilterOption[];
    services?: FilterOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e serviços' },
            { title: 'Profissionais' },
        ],
    },
});

const eligibleUserOptions = computed(() => props.eligibleUsers ?? []);

const search = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive' | 'deleted'>('all');
const unitFilter = ref('all');
const specialtyFilter = ref('all');
const serviceFilter = ref('all');
const workingHoursFilter = ref<'all' | 'with' | 'without'>('all');
const ongoingAbsenceFilter = ref(false);
const activeUnitFilter = ref<'all' | 'with' | 'without'>('all');
const operationalStatusFilter = ref<
    'all' | 'operational' | 'incomplete' | 'inactive'
>('all');

const indicators = computed(() => ({
    total: props.professionals.length,
    active: props.professionals.filter(
        (professional) =>
            !professional.deleted_at && professional.status === 'active',
    ).length,
    inactive: props.professionals.filter(
        (professional) =>
            !professional.deleted_at && professional.status === 'inactive',
    ).length,
    deleted: props.professionals.filter(
        (professional) => professional.deleted_at,
    ).length,
}));

const filteredProfessionals = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.professionals.filter((professional) => {
        const matchesSearch =
            term === '' ||
            professional.display_name.toLowerCase().includes(term) ||
            (professional.email ?? '').toLowerCase().includes(term);

        const matchesStatus =
            statusFilter.value === 'all'
                ? !professional.deleted_at
                : statusFilter.value === 'deleted'
                  ? Boolean(professional.deleted_at)
                  : !professional.deleted_at &&
                    professional.status === statusFilter.value;

        const matchesUnit =
            unitFilter.value === 'all' ||
            professional.unit_ids.includes(unitFilter.value);

        const matchesSpecialty =
            specialtyFilter.value === 'all' ||
            professional.specialty_ids.includes(specialtyFilter.value);

        const matchesService =
            serviceFilter.value === 'all' ||
            professional.service_ids.includes(serviceFilter.value);

        const matchesActiveUnit =
            activeUnitFilter.value === 'all' ||
            (activeUnitFilter.value === 'with'
                ? professional.has_active_unit
                : !professional.has_active_unit);

        const matchesWorkingHours =
            workingHoursFilter.value === 'all' ||
            (workingHoursFilter.value === 'with'
                ? professional.has_working_hours
                : !professional.has_working_hours);

        const matchesOngoingAbsence =
            !ongoingAbsenceFilter.value || professional.has_ongoing_absence;

        const matchesOperationalStatus =
            operationalStatusFilter.value === 'all' ||
            professional.operational_status === operationalStatusFilter.value;

        return (
            matchesSearch &&
            matchesStatus &&
            matchesUnit &&
            matchesSpecialty &&
            matchesService &&
            matchesActiveUnit &&
            matchesWorkingHours &&
            matchesOngoingAbsence &&
            matchesOperationalStatus
        );
    });
});

const hasAny = computed(() => props.professionals.length > 0);
const hasFilteredResults = computed(
    () => filteredProfessionals.value.length > 0,
);
const hasActiveFilters = computed(
    () =>
        search.value.trim() !== '' ||
        statusFilter.value !== 'all' ||
        unitFilter.value !== 'all' ||
        specialtyFilter.value !== 'all' ||
        serviceFilter.value !== 'all' ||
        activeUnitFilter.value !== 'all' ||
        workingHoursFilter.value !== 'all' ||
        ongoingAbsenceFilter.value ||
        operationalStatusFilter.value !== 'all',
);

const sheetOpen = ref(false);
const processingId = ref<string | null>(null);
const pendingDeletion = ref<ProfessionalRow | null>(null);

function openCreateSheet() {
    sheetOpen.value = true;
}

function goToEdit(professional: ProfessionalRow) {
    router.get(editRoute(professional.id).url);
}

function onFormSuccess() {
    sheetOpen.value = false;
}

function toggleActivate(professional: ProfessionalRow) {
    processingId.value = professional.id;
    const routeFn = professional.status === 'active' ? deactivate : activate;
    router.patch(
        routeFn(professional.id).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function confirmDelete() {
    if (!pendingDeletion.value) {
        return;
    }

    const id = pendingDeletion.value.id;
    processingId.value = id;
    router.delete(destroy(id).url, {
        preserveScroll: true,
        onFinish: () => {
            processingId.value = null;
            pendingDeletion.value = null;
        },
    });
}

function restore(professional: ProfessionalRow) {
    processingId.value = professional.id;
    router.post(
        restoreRoute(professional.id).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function formatDate(value: string): string {
    return formatDateTimeBr(value);
}
</script>

<template>
    <Head title="Profissionais" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Profissionais"
            description="Cadastro operacional dos profissionais da clínica — independente do acesso ao sistema. A vitrine pública de profissionais continua sendo gerenciada em 'Site da clínica'."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="agendas().url">
                        <CalendarClock class="size-4" />
                        Ver agendas
                    </Link>
                </Button>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Novo profissional
                </Button>
            </template>
        </PageHeader>

        <div v-if="hasAny" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Card>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">Total</p>
                    <p class="text-2xl font-semibold">{{ indicators.total }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">Ativos</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.active }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">Inativos</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.inactive }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">Excluídos</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.deleted }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div
            v-if="hasAny"
            class="flex flex-col gap-3 sm:flex-row sm:items-center"
        >
            <div class="relative sm:max-w-xs sm:flex-1">
                <Search
                    class="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Buscar por nome ou e-mail"
                    aria-label="Buscar profissionais por nome ou e-mail"
                    class="pl-8"
                />
            </div>

            <select
                v-model="statusFilter"
                aria-label="Filtrar profissionais por status"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Todos</option>
                <option value="active">Ativos</option>
                <option value="inactive">Inativos</option>
                <option value="deleted">Excluídos</option>
            </select>

            <select
                v-model="unitFilter"
                aria-label="Filtrar profissionais por unidade"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Todas as unidades</option>
                <option
                    v-for="unit in props.units ?? []"
                    :key="unit.id"
                    :value="unit.id"
                >
                    {{ unit.name }}
                </option>
            </select>

            <select
                v-model="specialtyFilter"
                aria-label="Filtrar profissionais por especialidade"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Todas as especialidades</option>
                <option
                    v-for="specialty in props.specialties ?? []"
                    :key="specialty.id"
                    :value="specialty.id"
                >
                    {{ specialty.name }}
                </option>
            </select>

            <select
                v-model="serviceFilter"
                aria-label="Filtrar profissionais por serviço"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Todos os serviços</option>
                <option
                    v-for="service in props.services ?? []"
                    :key="service.id"
                    :value="service.id"
                >
                    {{ service.name }}
                </option>
            </select>

            <select
                v-model="activeUnitFilter"
                aria-label="Filtrar profissionais por unidade ativa"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Com ou sem unidade ativa</option>
                <option value="with">Com unidade ativa</option>
                <option value="without">Sem unidade ativa</option>
            </select>

            <select
                v-model="workingHoursFilter"
                aria-label="Filtrar profissionais por jornada"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Com ou sem jornada</option>
                <option value="with">Com jornada</option>
                <option value="without">Sem jornada</option>
            </select>

            <select
                v-model="operationalStatusFilter"
                aria-label="Filtrar profissionais por situação operacional"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Qualquer situação operacional</option>
                <option value="operational">Operacional</option>
                <option value="incomplete">Configuração incompleta</option>
                <option value="inactive">Inativo</option>
            </select>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="ongoingAbsenceFilter" type="checkbox" />
                Com ausência em andamento
            </label>
        </div>

        <EmptyState
            v-if="!hasAny"
            title="Nenhum profissional cadastrado ainda."
            description="Cadastre o primeiro profissional da sua clínica para começar."
        >
            <template #action>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Cadastrar primeiro profissional
                </Button>
            </template>
        </EmptyState>

        <EmptyState
            v-else-if="!hasFilteredResults"
            title="Nenhum profissional corresponde aos filtros informados."
        />

        <template v-else>
            <div class="hidden overflow-x-auto rounded-md border md:block">
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/50 text-left text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-2 font-medium">Profissional</th>
                            <th class="px-4 py-2 font-medium">Contato</th>
                            <th class="px-4 py-2 font-medium">Documento</th>
                            <th class="px-4 py-2 font-medium">
                                Usuário vinculado
                            </th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 font-medium">Atualizado em</th>
                            <th class="px-4 py-2 font-medium">
                                <span class="sr-only">Ações</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="professional in filteredProfessionals"
                            :key="professional.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-3 font-medium">
                                <div class="flex items-center gap-2">
                                    <img
                                        v-if="professional.photo_url"
                                        :src="professional.photo_url"
                                        :alt="`Foto de ${professional.display_name}`"
                                        class="size-8 rounded-full object-cover"
                                    />
                                    <UserRound
                                        v-else
                                        class="size-8 rounded-full border p-1 text-muted-foreground"
                                    />
                                    {{ professional.display_name }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div>{{ professional.email || '—' }}</div>
                                <div>{{ professional.phone || '—' }}</div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ professional.document || '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ professional.linked_user_name || 'Nenhum' }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :status="professional.status"
                                    :deleted-at="professional.deleted_at"
                                />
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{
                                        OPERATIONAL_STATUS_LABELS[
                                            professional.operational_status
                                        ]
                                    }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ formatDate(professional.updated_at) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <ProfessionalRowActions
                                    :professional="professional"
                                    :disabled="processingId === professional.id"
                                    @edit="goToEdit(professional)"
                                    @activate="toggleActivate(professional)"
                                    @deactivate="toggleActivate(professional)"
                                    @delete="pendingDeletion = professional"
                                    @restore="restore(professional)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3 md:hidden">
                <Card
                    v-for="professional in filteredProfessionals"
                    :key="professional.id"
                >
                    <CardContent class="flex flex-col gap-2 py-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <img
                                    v-if="professional.photo_url"
                                    :src="professional.photo_url"
                                    :alt="`Foto de ${professional.display_name}`"
                                    class="size-8 rounded-full object-cover"
                                />
                                <UserRound
                                    v-else
                                    class="size-8 rounded-full border p-1 text-muted-foreground"
                                />
                                <p class="font-medium">
                                    {{ professional.display_name }}
                                </p>
                            </div>
                            <ProfessionalRowActions
                                :professional="professional"
                                :disabled="processingId === professional.id"
                                @edit="goToEdit(professional)"
                                @activate="toggleActivate(professional)"
                                @deactivate="toggleActivate(professional)"
                                @delete="pendingDeletion = professional"
                                @restore="restore(professional)"
                            />
                        </div>

                        <StatusBadge
                            :status="professional.status"
                            :deleted-at="professional.deleted_at"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{
                                OPERATIONAL_STATUS_LABELS[
                                    professional.operational_status
                                ]
                            }}
                        </p>

                        <p class="text-sm text-muted-foreground">
                            {{ professional.email || 'Sem e-mail' }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </template>

        <p
            v-if="hasAny && hasActiveFilters"
            class="text-sm text-muted-foreground"
        >
            {{ filteredProfessionals.length }} de
            {{ indicators.total }} profissionais
        </p>

        <Sheet v-model:open="sheetOpen">
            <SheetContent
                side="right"
                class="w-full gap-0 overflow-y-auto sm:max-w-xl"
            >
                <SheetHeader>
                    <SheetTitle>Novo profissional</SheetTitle>
                    <SheetDescription>
                        Cadastre um novo profissional da clínica.
                    </SheetDescription>
                </SheetHeader>

                <div class="px-4 pb-6">
                    <ProfessionalForm
                        v-if="sheetOpen"
                        mode="create"
                        :eligible-users="eligibleUserOptions"
                        @success="onFormSuccess"
                        @cancel="sheetOpen = false"
                    />
                </div>
            </SheetContent>
        </Sheet>

        <Dialog
            :open="pendingDeletion !== null"
            @update:open="(open) => !open && (pendingDeletion = null)"
        >
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Excluir profissional?</DialogTitle>
                    <DialogDescription>
                        Este registro será removido da operação, mas seu
                        histórico será preservado. Você poderá restaurá-lo
                        depois, se necessário.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancelar</Button>
                    </DialogClose>
                    <Button variant="destructive" @click="confirmDelete"
                        >Excluir</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

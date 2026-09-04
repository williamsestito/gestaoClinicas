<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
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
import type { EditableUnit } from '@/components/units/UnitForm.vue';
import UnitForm from '@/components/units/UnitForm.vue';
import UnitRowActions from '@/components/units/UnitRowActions.vue';
import { dashboard } from '@/routes';
import {
    destroy,
    headquarters as headquartersRoute,
    restore as restoreRoute,
    status as statusRoute,
} from '@/routes/settings/units';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Configurações da clínica' },
            { title: 'Unidades' },
        ],
    },
});

const props = defineProps<{
    units: EditableUnit[];
    states: string[];
}>();

const search = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive' | 'deleted'>('all');
const onlyHeadquarters = ref(false);

const indicators = computed(() => ({
    total: props.units.length,
    active: props.units.filter(
        (unit) => !unit.deleted_at && unit.status === 'active',
    ).length,
    inactive: props.units.filter(
        (unit) => !unit.deleted_at && unit.status === 'inactive',
    ).length,
    deleted: props.units.filter((unit) => unit.deleted_at).length,
}));

const filteredUnits = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.units.filter((unit) => {
        const matchesSearch =
            term === '' ||
            unit.name.toLowerCase().includes(term) ||
            unit.code.toLowerCase().includes(term);

        const matchesStatus =
            statusFilter.value === 'all'
                ? true
                : statusFilter.value === 'deleted'
                  ? Boolean(unit.deleted_at)
                  : !unit.deleted_at && unit.status === statusFilter.value;

        const matchesHeadquarters =
            !onlyHeadquarters.value || unit.is_headquarters;

        return matchesSearch && matchesStatus && matchesHeadquarters;
    });
});

const hasAnyUnits = computed(() => props.units.length > 0);
const hasFilteredResults = computed(() => filteredUnits.value.length > 0);
const hasActiveFilters = computed(
    () =>
        search.value.trim() !== '' ||
        statusFilter.value !== 'all' ||
        onlyHeadquarters.value,
);

const sheetOpen = ref(false);
const sheetMode = ref<'create' | 'edit'>('create');
const editingUnit = ref<EditableUnit | null>(null);
const processingUnitId = ref<string | null>(null);
const unitPendingDeletion = ref<EditableUnit | null>(null);

function openCreateSheet() {
    sheetMode.value = 'create';
    editingUnit.value = null;
    sheetOpen.value = true;
}

function openEditSheet(unit: EditableUnit) {
    sheetMode.value = 'edit';
    editingUnit.value = unit;
    sheetOpen.value = true;
}

function onFormSuccess() {
    sheetOpen.value = false;
}

function toggleStatus(unit: EditableUnit) {
    processingUnitId.value = unit.id;
    router.patch(
        statusRoute(unit.id).url,
        { active: unit.status !== 'active' },
        {
            preserveScroll: true,
            onFinish: () => (processingUnitId.value = null),
        },
    );
}

function makeHeadquarters(unit: EditableUnit) {
    processingUnitId.value = unit.id;
    router.put(
        headquartersRoute(unit.id).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => (processingUnitId.value = null),
        },
    );
}

function confirmDelete() {
    if (!unitPendingDeletion.value) {
        return;
    }

    const id = unitPendingDeletion.value.id;
    processingUnitId.value = id;
    router.delete(destroy(id).url, {
        preserveScroll: true,
        onFinish: () => {
            processingUnitId.value = null;
            unitPendingDeletion.value = null;
        },
    });
}

function restore(unit: EditableUnit) {
    processingUnitId.value = unit.id;
    router.post(
        restoreRoute(unit.id).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => (processingUnitId.value = null),
        },
    );
}
</script>

<template>
    <Head title="Unidades" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Unidades"
            description="Gerencie as unidades, filiais, endereços e horários de atendimento da clínica."
        >
            <template #actions>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Nova unidade
                </Button>
            </template>
        </PageHeader>

        <div v-if="hasAnyUnits" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Card>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">Total</p>
                    <p class="text-2xl font-semibold">{{ indicators.total }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">Ativas</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.active }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">Inativas</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.inactive }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">Removidas</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.deleted }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div
            v-if="hasAnyUnits"
            class="flex flex-col gap-3 sm:flex-row sm:items-center"
        >
            <div class="relative sm:max-w-xs sm:flex-1">
                <Search
                    class="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Buscar por nome ou código"
                    aria-label="Buscar unidades por nome ou código"
                    class="pl-8"
                />
            </div>

            <select
                v-model="statusFilter"
                aria-label="Filtrar unidades por status"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Todas</option>
                <option value="active">Ativas</option>
                <option value="inactive">Inativas</option>
                <option value="deleted">Excluídas</option>
            </select>

            <label
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <input
                    v-model="onlyHeadquarters"
                    type="checkbox"
                    class="size-4 rounded border-input"
                />
                Somente matriz
            </label>
        </div>

        <EmptyState
            v-if="!hasAnyUnits"
            title="Nenhuma unidade cadastrada ainda."
            description="Cadastre a primeira unidade da sua clínica para começar."
        >
            <template #action>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Cadastrar primeira unidade
                </Button>
            </template>
        </EmptyState>

        <EmptyState
            v-else-if="!hasFilteredResults"
            title="Nenhuma unidade corresponde aos filtros informados."
        />

        <template v-else>
            <div class="hidden overflow-x-auto rounded-md border md:block">
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/50 text-left text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-2 font-medium">Unidade</th>
                            <th class="px-4 py-2 font-medium">Código</th>
                            <th class="px-4 py-2 font-medium">Cidade/UF</th>
                            <th class="px-4 py-2 font-medium">Contato</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 font-medium">
                                <span class="sr-only">Ações</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="unit in filteredUnits"
                            :key="unit.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ unit.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ unit.code }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <template v-if="unit.address">
                                    {{ unit.address.city }}/{{
                                        unit.address.state
                                    }}
                                </template>
                                <template v-else>—</template>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ unit.phone || unit.whatsapp || '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <StatusBadge
                                        :status="unit.status"
                                        :deleted-at="unit.deleted_at"
                                        :highlight-label="
                                            unit.is_headquarters
                                                ? 'Matriz'
                                                : undefined
                                        "
                                    />
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <UnitRowActions
                                    :unit="unit"
                                    :disabled="processingUnitId === unit.id"
                                    @edit="openEditSheet(unit)"
                                    @toggle-status="toggleStatus(unit)"
                                    @make-headquarters="makeHeadquarters(unit)"
                                    @delete="unitPendingDeletion = unit"
                                    @restore="restore(unit)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3 md:hidden">
                <Card v-for="unit in filteredUnits" :key="unit.id">
                    <CardContent class="flex flex-col gap-2 py-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-medium">{{ unit.name }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ unit.code }}
                                </p>
                            </div>
                            <UnitRowActions
                                :unit="unit"
                                :disabled="processingUnitId === unit.id"
                                @edit="openEditSheet(unit)"
                                @toggle-status="toggleStatus(unit)"
                                @make-headquarters="makeHeadquarters(unit)"
                                @delete="unitPendingDeletion = unit"
                                @restore="restore(unit)"
                            />
                        </div>

                        <div class="flex flex-wrap gap-1">
                            <StatusBadge
                                :status="unit.status"
                                :deleted-at="unit.deleted_at"
                                :highlight-label="
                                    unit.is_headquarters ? 'Matriz' : undefined
                                "
                            />
                        </div>

                        <p class="text-sm text-muted-foreground">
                            <template v-if="unit.address"
                                >{{ unit.address.city }}/{{
                                    unit.address.state
                                }}</template
                            >
                            <template v-if="unit.phone || unit.whatsapp">
                                {{ unit.address ? ' · ' : ''
                                }}{{ unit.phone || unit.whatsapp }}
                            </template>
                        </p>
                    </CardContent>
                </Card>
            </div>
        </template>

        <p
            v-if="hasAnyUnits && hasActiveFilters"
            class="text-sm text-muted-foreground"
        >
            {{ filteredUnits.length }} de {{ indicators.total }} unidades
        </p>

        <Sheet v-model:open="sheetOpen">
            <SheetContent
                side="right"
                class="w-full gap-0 overflow-y-auto sm:max-w-xl"
            >
                <SheetHeader>
                    <SheetTitle>
                        {{
                            sheetMode === 'create'
                                ? 'Nova unidade'
                                : 'Editar unidade'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        {{
                            sheetMode === 'create'
                                ? 'Cadastre uma nova unidade da organização.'
                                : `Atualize os dados de ${editingUnit?.name ?? 'unidade'}.`
                        }}
                    </SheetDescription>
                </SheetHeader>

                <div class="px-4 pb-6">
                    <UnitForm
                        v-if="sheetOpen"
                        :key="editingUnit?.id ?? 'create'"
                        :mode="sheetMode"
                        :unit="editingUnit ?? undefined"
                        :states="states"
                        @success="onFormSuccess"
                        @cancel="sheetOpen = false"
                    />
                </div>
            </SheetContent>
        </Sheet>

        <Dialog
            :open="unitPendingDeletion !== null"
            @update:open="(open) => !open && (unitPendingDeletion = null)"
        >
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Excluir unidade?</DialogTitle>
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

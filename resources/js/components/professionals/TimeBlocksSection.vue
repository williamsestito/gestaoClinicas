<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { MoreHorizontal, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    activate,
    deactivate,
    destroy,
    restore as restoreRoute,
} from '@/routes/settings/professionals/time-blocks';
import type { EditableTimeBlock, UnitOption } from './TimeBlockForm.vue';
import TimeBlockForm from './TimeBlockForm.vue';

export type TimeBlockRow = {
    id: string;
    type: string;
    scope: 'all_units' | 'specific_unit';
    unit: { id: string; name: string } | null;
    timezone: string;
    starts_at: string;
    ends_at: string;
    is_all_day: boolean;
    reason: string | null;
    internal_notes: string | null;
    status: 'active' | 'inactive';
    temporal_status: 'future' | 'ongoing' | 'ended' | 'inactive' | 'deleted';
    can_manage: boolean;
    deleted_at: string | null;
};

const props = defineProps<{
    professionalId: string;
    timeBlocks: TimeBlockRow[];
    eligibleUnits: UnitOption[];
}>();

const TYPE_LABELS: Record<string, string> = {
    vacation: 'Férias',
    day_off: 'Folga',
    absence: 'Ausência',
    administrative_block: 'Bloqueio administrativo',
    external_event: 'Evento externo',
    partial_unavailability: 'Indisponibilidade parcial',
};

const TEMPORAL_LABELS: Record<TimeBlockRow['temporal_status'], string> = {
    future: 'Futuro',
    ongoing: 'Em andamento',
    ended: 'Encerrado',
    inactive: 'Inativo',
    deleted: 'Excluído',
};

const typeFilter = ref<string>('all');
const statusFilter = ref<'all' | 'active' | 'inactive' | 'deleted'>('all');

const filteredTimeBlocks = computed(() =>
    props.timeBlocks.filter((block) => {
        const matchesType =
            typeFilter.value === 'all' || block.type === typeFilter.value;
        const matchesStatus =
            statusFilter.value === 'all'
                ? !block.deleted_at
                : statusFilter.value === 'deleted'
                  ? Boolean(block.deleted_at)
                  : !block.deleted_at && block.status === statusFilter.value;

        return matchesType && matchesStatus;
    }),
);

function formatLocalRange(block: TimeBlockRow): string {
    const formatter = new Intl.DateTimeFormat('pt-BR', {
        timeZone: block.timezone,
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        ...(block.is_all_day ? {} : { hour: '2-digit', minute: '2-digit' }),
    });

    return `${formatter.format(new Date(block.starts_at))} até ${formatter.format(new Date(block.ends_at))}`;
}

const sheetOpen = ref(false);
const editingTimeBlock = ref<EditableTimeBlock | null>(null);
const processingId = ref<string | null>(null);
const pendingRemoval = ref<TimeBlockRow | null>(null);

function openCreate() {
    editingTimeBlock.value = null;
    sheetOpen.value = true;
}

function openEdit(row: TimeBlockRow) {
    editingTimeBlock.value = {
        id: row.id,
        type: row.type,
        scope: row.scope,
        unit: row.unit,
        timezone: row.timezone,
        starts_at: row.starts_at,
        ends_at: row.ends_at,
        is_all_day: row.is_all_day,
        reason: row.reason,
        internal_notes: row.internal_notes,
    };
    sheetOpen.value = true;
}

function onFormSuccess() {
    sheetOpen.value = false;
}

function toggleActivate(row: TimeBlockRow) {
    processingId.value = row.id;
    const routeFn = row.status === 'active' ? deactivate : activate;
    router.patch(
        routeFn([props.professionalId, row.id]).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function confirmRemove() {
    if (!pendingRemoval.value) {
        return;
    }

    const id = pendingRemoval.value.id;
    processingId.value = id;
    router.delete(destroy([props.professionalId, id]).url, {
        preserveScroll: true,
        onFinish: () => {
            processingId.value = null;
            pendingRemoval.value = null;
        },
    });
}

function restore(row: TimeBlockRow) {
    processingId.value = row.id;
    router.post(
        restoreRoute([props.professionalId, row.id]).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}
</script>

<template>
    <div class="grid gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <select
                v-model="typeFilter"
                aria-label="Filtrar bloqueios por tipo"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Todos os tipos</option>
                <option
                    v-for="(label, value) in TYPE_LABELS"
                    :key="value"
                    :value="value"
                >
                    {{ label }}
                </option>
            </select>

            <select
                v-model="statusFilter"
                aria-label="Filtrar bloqueios por status"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Ativos e inativos</option>
                <option value="active">Ativos</option>
                <option value="inactive">Inativos</option>
                <option value="deleted">Excluídos</option>
            </select>

            <Button type="button" size="sm" @click="openCreate">
                <Plus class="size-4" />
                Novo bloqueio
            </Button>
        </div>

        <EmptyState
            v-if="timeBlocks.length === 0"
            title="Este profissional ainda não possui ausências ou bloqueios cadastrados."
        />
        <EmptyState
            v-else-if="filteredTimeBlocks.length === 0"
            title="Nenhum bloqueio corresponde aos filtros informados."
        />

        <ul v-else class="grid gap-2">
            <li
                v-for="row in filteredTimeBlocks"
                :key="row.id"
                class="flex items-center justify-between gap-2 rounded-md border p-3"
            >
                <div>
                    <p class="text-sm font-medium">
                        {{ TYPE_LABELS[row.type] ?? row.type }}
                        <span class="ml-1 text-xs text-muted-foreground">
                            ({{ TEMPORAL_LABELS[row.temporal_status] }})</span
                        >
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            row.scope === 'all_units'
                                ? 'Todas as unidades'
                                : row.unit?.name
                        }}
                        · {{ formatLocalRange(row) }}
                        <span v-if="row.is_all_day"> · Dia inteiro</span>
                    </p>
                    <p v-if="row.reason" class="text-xs text-muted-foreground">
                        {{ row.reason }}
                    </p>
                    <p
                        v-if="row.internal_notes"
                        class="text-xs text-muted-foreground italic"
                    >
                        {{ row.internal_notes }}
                    </p>
                </div>

                <DropdownMenu v-if="row.can_manage">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            :disabled="processingId === row.id"
                            :aria-label="`Ações para o bloqueio ${TYPE_LABELS[row.type] ?? row.type}`"
                        >
                            <MoreHorizontal class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <template v-if="row.deleted_at">
                            <DropdownMenuItem @select="restore(row)">
                                Restaurar
                            </DropdownMenuItem>
                        </template>
                        <template v-else>
                            <DropdownMenuItem @select="openEdit(row)">
                                Editar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="row.status === 'active'"
                                @select="toggleActivate(row)"
                            >
                                Inativar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-else
                                @select="toggleActivate(row)"
                            >
                                Ativar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                variant="destructive"
                                @select="pendingRemoval = row"
                            >
                                Excluir
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
            </li>
        </ul>

        <Sheet v-model:open="sheetOpen">
            <SheetContent
                side="right"
                class="w-full gap-0 overflow-y-auto sm:max-w-lg"
            >
                <SheetHeader>
                    <SheetTitle>
                        {{
                            editingTimeBlock
                                ? 'Editar bloqueio'
                                : 'Novo bloqueio'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        Ausências e bloqueios reduzem a disponibilidade regular
                        do profissional.
                    </SheetDescription>
                </SheetHeader>
                <div class="px-4 pb-6">
                    <TimeBlockForm
                        v-if="sheetOpen"
                        :key="editingTimeBlock?.id ?? 'create'"
                        :mode="editingTimeBlock ? 'edit' : 'create'"
                        :professional-id="professionalId"
                        :eligible-units="eligibleUnits"
                        :time-block="editingTimeBlock ?? undefined"
                        @success="onFormSuccess"
                        @cancel="sheetOpen = false"
                    />
                </div>
            </SheetContent>
        </Sheet>

        <Dialog
            :open="pendingRemoval !== null"
            @update:open="(open) => !open && (pendingRemoval = null)"
        >
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Excluir bloqueio?</DialogTitle>
                    <DialogDescription>
                        Este bloqueio será removido da operação, mas seu
                        histórico será preservado.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancelar</Button>
                    </DialogClose>
                    <Button variant="destructive" @click="confirmRemove"
                        >Excluir</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

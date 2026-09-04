<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { MoreHorizontal, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
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
    copy,
    deactivate,
    destroy,
    restore as restoreRoute,
} from '@/routes/settings/professionals/working-hours';
import type { EditableWorkingHour } from './WorkingHourForm.vue';
import WorkingHourForm from './WorkingHourForm.vue';
import WorkingHourWizard from './WorkingHourWizard.vue';

export type WorkingHourRow = {
    id: string;
    weekday: number;
    starts_at: string;
    ends_at: string;
    effective_from: string | null;
    effective_until: string | null;
    status: 'active' | 'inactive';
    vigency_status: 'scheduled' | 'in_effect' | 'ended';
    is_within_opening_hours: boolean;
    deleted_at: string | null;
};

export type AvailabilityUnit = {
    professional_unit_id: string;
    unit: { id: string; name: string; timezone: string };
    unit_link_status: 'active' | 'inactive';
    opening_hours: Array<{
        day_of_week: number;
        opens_at: string;
        closes_at: string;
    }>;
    can_manage: boolean;
    working_hours: WorkingHourRow[];
};

const props = defineProps<{
    professionalId: string;
    unit: AvailabilityUnit;
}>();

const WEEKDAYS = [
    { value: 1, label: 'Segunda-feira' },
    { value: 2, label: 'Terça-feira' },
    { value: 3, label: 'Quarta-feira' },
    { value: 4, label: 'Quinta-feira' },
    { value: 5, label: 'Sexta-feira' },
    { value: 6, label: 'Sábado' },
    { value: 0, label: 'Domingo' },
];

const vigencyLabels: Record<WorkingHourRow['vigency_status'], string> = {
    scheduled: 'Agendado',
    in_effect: 'Vigente',
    ended: 'Encerrado',
};

function intervalsFor(weekday: number): WorkingHourRow[] {
    return props.unit.working_hours.filter((wh) => wh.weekday === weekday);
}

function openingHoursFor(weekday: number) {
    return props.unit.opening_hours.filter((oh) => oh.day_of_week === weekday);
}

const sheetOpen = ref(false);
const editingWorkingHour = ref<EditableWorkingHour | null>(null);
const sheetWeekday = ref(1);
const processingId = ref<string | null>(null);
const pendingRemoval = ref<WorkingHourRow | null>(null);

function openCreate(weekday: number) {
    editingWorkingHour.value = null;
    sheetWeekday.value = weekday;
    sheetOpen.value = true;
}

function openEdit(row: WorkingHourRow) {
    editingWorkingHour.value = {
        id: row.id,
        weekday: row.weekday,
        starts_at: row.starts_at,
        ends_at: row.ends_at,
        effective_from: row.effective_from,
        effective_until: row.effective_until,
    };
    sheetOpen.value = true;
}

function onFormSuccess() {
    sheetOpen.value = false;
}

const wizardOpen = ref(false);

function onWizardSuccess() {
    wizardOpen.value = false;
}

function toggleActivate(row: WorkingHourRow) {
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

function restore(row: WorkingHourRow) {
    processingId.value = row.id;
    router.post(
        restoreRoute([props.professionalId, row.id]).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

const copyDialogOpen = ref(false);
const copySourceWeekday = ref<number | null>(null);
const copyTargetWeekdays = ref<number[]>([]);
const copyProcessing = ref(false);
const copyErrors = ref<string[]>([]);

function openCopyDialog(weekday: number) {
    copySourceWeekday.value = weekday;
    copyTargetWeekdays.value = [];
    copyErrors.value = [];
    copyDialogOpen.value = true;
}

function toggleTargetWeekday(weekday: number, checked: boolean) {
    if (checked) {
        copyTargetWeekdays.value = [...copyTargetWeekdays.value, weekday];

        return;
    }

    copyTargetWeekdays.value = copyTargetWeekdays.value.filter(
        (value) => value !== weekday,
    );
}

const copyTargetOptions = computed(() =>
    WEEKDAYS.filter((day) => day.value !== copySourceWeekday.value),
);

function submitCopy() {
    if (
        copySourceWeekday.value === null ||
        copyTargetWeekdays.value.length === 0
    ) {
        return;
    }

    copyProcessing.value = true;
    copyErrors.value = [];
    router.post(
        copy([props.professionalId, props.unit.professional_unit_id]).url,
        {
            source_weekday: copySourceWeekday.value,
            target_weekdays: copyTargetWeekdays.value,
        },
        {
            preserveScroll: true,
            // A cópia é tudo-ou-nada: se qualquer dia de destino conflitar
            // (fora do funcionamento da unidade, sobreposição etc.), nada é
            // copiado — o diálogo precisa continuar aberto mostrando o
            // motivo, nunca fechar silenciosamente como se tivesse
            // funcionado.
            onSuccess: () => {
                copyDialogOpen.value = false;
            },
            onError: (errors) => {
                copyErrors.value = Object.values(errors);
            },
            onFinish: () => {
                copyProcessing.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="grid gap-3 rounded-md border p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold">{{ unit.unit.name }}</h3>
                <p class="text-muted-foreground text-xs">
                    Fuso: {{ unit.unit.timezone }}
                    <span v-if="unit.unit_link_status === 'inactive'">
                        · Vínculo inativo</span
                    >
                </p>
            </div>
            <Button
                v-if="unit.can_manage"
                type="button"
                variant="outline"
                size="sm"
                @click="wizardOpen = true"
            >
                Configurar vigência
            </Button>
        </div>

        <div class="overflow-x-auto rounded-md border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-muted-foreground border-b text-left"
                >
                    <tr>
                        <th class="px-3 py-2 font-medium">Dia da semana</th>
                        <th class="px-3 py-2 font-medium">Início</th>
                        <th class="px-3 py-2 font-medium">Término</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 font-medium">
                            <span class="sr-only">Ações</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="day in WEEKDAYS" :key="day.value">
                        <tr
                            v-if="intervalsFor(day.value).length === 0"
                            class="border-b last:border-0"
                        >
                            <td class="px-3 py-2.5 align-top font-medium">
                                {{ day.label }}
                            </td>
                            <td
                                colspan="3"
                                class="text-muted-foreground px-3 py-2.5 align-top"
                            >
                                {{
                                    openingHoursFor(day.value).length === 0
                                        ? 'Unidade fechada neste dia.'
                                        : 'Nenhum horário cadastrado.'
                                }}
                            </td>
                            <td class="px-3 py-2.5 text-right align-top">
                                <Button
                                    v-if="unit.can_manage"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="`Adicionar intervalo em ${day.label}`"
                                    @click="openCreate(day.value)"
                                >
                                    <Plus class="size-4" />
                                    Adicionar
                                </Button>
                            </td>
                        </tr>

                        <tr
                            v-for="(row, index) in intervalsFor(day.value)"
                            :key="row.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-3 py-2.5 align-top font-medium">
                                {{ index === 0 ? day.label : '' }}
                            </td>
                            <td class="px-3 py-2.5 align-top">
                                {{ row.starts_at }}
                            </td>
                            <td class="px-3 py-2.5 align-top">
                                {{ row.ends_at }}
                            </td>
                            <td class="px-3 py-2.5 align-top">
                                <p>
                                    {{
                                        row.deleted_at
                                            ? 'Excluído'
                                            : row.status === 'active'
                                              ? 'Ativo'
                                              : 'Inativo'
                                    }}
                                    · {{ vigencyLabels[row.vigency_status] }}
                                </p>
                                <p
                                    v-if="!row.is_within_opening_hours"
                                    class="text-xs text-amber-600 dark:text-amber-400"
                                >
                                    Fora do funcionamento atual da unidade.
                                </p>
                            </td>
                            <td class="px-3 py-2.5 text-right align-top">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <template
                                        v-if="unit.can_manage && index === 0"
                                    >
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            @click="openCopyDialog(day.value)"
                                        >
                                            Copiar
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="`Adicionar intervalo em ${day.label}`"
                                            @click="openCreate(day.value)"
                                        >
                                            <Plus class="size-4" />
                                        </Button>
                                    </template>

                                    <DropdownMenu v-if="unit.can_manage">
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                :disabled="
                                                    processingId === row.id
                                                "
                                                :aria-label="`Ações para o intervalo de ${row.starts_at} às ${row.ends_at}`"
                                            >
                                                <MoreHorizontal
                                                    class="size-4"
                                                />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <template v-if="row.deleted_at">
                                                <DropdownMenuItem
                                                    @select="restore(row)"
                                                >
                                                    Restaurar
                                                </DropdownMenuItem>
                                            </template>
                                            <template v-else>
                                                <DropdownMenuItem
                                                    @select="openEdit(row)"
                                                >
                                                    Editar
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="
                                                        row.status === 'active'
                                                    "
                                                    @select="
                                                        toggleActivate(row)
                                                    "
                                                >
                                                    Inativar
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-else
                                                    @select="
                                                        toggleActivate(row)
                                                    "
                                                >
                                                    Ativar
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    @select="
                                                        pendingRemoval = row
                                                    "
                                                >
                                                    Excluir
                                                </DropdownMenuItem>
                                            </template>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <Sheet v-model:open="sheetOpen">
            <SheetContent side="right" class="w-full gap-0 sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>
                        {{
                            editingWorkingHour
                                ? 'Editar horário'
                                : 'Novo horário'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        Horário local da unidade ({{ unit.unit.timezone }}).
                    </SheetDescription>
                </SheetHeader>
                <div class="px-4 pb-6">
                    <WorkingHourForm
                        v-if="sheetOpen"
                        :key="
                            editingWorkingHour?.id ?? `create-${sheetWeekday}`
                        "
                        :mode="editingWorkingHour ? 'edit' : 'create'"
                        :professional-id="professionalId"
                        :professional-unit-id="unit.professional_unit_id"
                        :default-weekday="sheetWeekday"
                        :working-hour="editingWorkingHour ?? undefined"
                        @success="onFormSuccess"
                        @cancel="sheetOpen = false"
                    />
                </div>
            </SheetContent>
        </Sheet>

        <Sheet v-model:open="wizardOpen">
            <SheetContent side="right" class="w-full gap-0 sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Configurar vigência em lote</SheetTitle>
                    <SheetDescription>
                        Defina de uma vez a vigência, os dias da semana e os
                        intervalos para {{ unit.unit.name }} (fuso
                        {{ unit.unit.timezone }}). Para ajustes pontuais, use
                        "Adicionar intervalo" em um único dia.
                    </SheetDescription>
                </SheetHeader>
                <div class="px-4 pb-6">
                    <WorkingHourWizard
                        v-if="wizardOpen"
                        :professional-id="professionalId"
                        :professional-unit-id="unit.professional_unit_id"
                        @success="onWizardSuccess"
                        @cancel="wizardOpen = false"
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
                    <DialogTitle>Excluir horário?</DialogTitle>
                    <DialogDescription>
                        Este horário será removido da operação, mas seu
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

        <Dialog v-model:open="copyDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Copiar horários</DialogTitle>
                    <DialogDescription>
                        Os horários serão copiados para os dias selecionados.
                        Conflitos serão validados antes de salvar — nada será
                        sobrescrito.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-1">
                    <label
                        v-for="day in copyTargetOptions"
                        :key="day.value"
                        class="flex items-center gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            :checked="copyTargetWeekdays.includes(day.value)"
                            @change="
                                toggleTargetWeekday(
                                    day.value,
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                        {{ day.label }}
                    </label>
                </div>
                <div
                    v-if="copyErrors.length > 0"
                    role="alert"
                    class="border-destructive/50 bg-destructive/10 text-destructive grid gap-1 rounded-md border p-3 text-sm"
                >
                    <p v-for="(message, index) in copyErrors" :key="index">
                        {{ message }}
                    </p>
                </div>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancelar</Button>
                    </DialogClose>
                    <Button
                        :disabled="
                            copyTargetWeekdays.length === 0 || copyProcessing
                        "
                        @click="submitCopy"
                    >
                        Copiar horários
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

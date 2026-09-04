<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { MoreHorizontal } from '@lucide/vue';
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
import { formatCurrencyBrl } from '@/lib/masks';
import {
    activate,
    deactivate,
    destroy,
    restore as restoreRoute,
} from '@/routes/settings/professionals/services';
import type {
    EditableServiceLink,
    ProfessionalUnitOption,
    ServiceOption,
} from './ServiceAssignmentForm.vue';
import ServiceAssignmentForm from './ServiceAssignmentForm.vue';

type EffectiveValue = {
    default: number | null;
    custom: number | null;
    effective: number | null;
    is_inherited: boolean;
};

export type ServiceLink = {
    id: string;
    service: { id: string; name: string };
    status: 'active' | 'inactive';
    unit_scope: 'all_compatible_units' | 'selected_units' | 'none';
    selected_unit_ids: string[];
    compatible_units: string[];
    duration_minutes: EffectiveValue;
    price_cents: EffectiveValue;
    buffer_before_minutes: EffectiveValue;
    buffer_after_minutes: EffectiveValue;
    deleted_at: string | null;
};

const unitScopeLabels: Record<ServiceLink['unit_scope'], string> = {
    all_compatible_units: 'Todas as unidades compatíveis',
    selected_units: 'Unidades selecionadas',
    none: 'Nenhuma unidade',
};

const props = defineProps<{
    professionalId: string;
    links: ServiceLink[];
    eligibleServices: ServiceOption[];
    professionalUnits: ProfessionalUnitOption[];
}>();

function unitName(unitId: string): string {
    return (
        props.professionalUnits.find((unit) => unit.id === unitId)?.name ??
        unitId
    );
}

function formatPrice(cents: number | null): string {
    return cents === null ? 'Sem preço definido' : formatCurrencyBrl(cents);
}

const sheetOpen = ref(false);
const editingLink = ref<ServiceLink | null>(null);
const processingId = ref<string | null>(null);
const pendingRemoval = ref<ServiceLink | null>(null);

const editableLink = computed<EditableServiceLink | undefined>(() => {
    if (!editingLink.value) {
        return undefined;
    }

    const link = editingLink.value;

    return {
        id: link.id,
        custom_duration_minutes: link.duration_minutes.custom,
        custom_price:
            link.price_cents.custom === null
                ? null
                : link.price_cents.custom / 100,
        custom_buffer_before_minutes: link.buffer_before_minutes.custom,
        custom_buffer_after_minutes: link.buffer_after_minutes.custom,
        unit_scope: link.unit_scope,
        unit_ids: link.selected_unit_ids,
        defaults: {
            duration_minutes: link.duration_minutes.default ?? 30,
            price:
                link.price_cents.default === null
                    ? null
                    : link.price_cents.default / 100,
            buffer_before_minutes: link.buffer_before_minutes.default ?? 0,
            buffer_after_minutes: link.buffer_after_minutes.default ?? 0,
        },
    };
});

function openCreate() {
    editingLink.value = null;
    sheetOpen.value = true;
}

function openEdit(link: ServiceLink) {
    editingLink.value = link;
    sheetOpen.value = true;
}

function onFormSuccess() {
    sheetOpen.value = false;
}

function toggleActivate(link: ServiceLink) {
    processingId.value = link.id;
    const routeFn = link.status === 'active' ? deactivate : activate;
    router.patch(
        routeFn([props.professionalId, link.id]).url,
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

function restore(link: ServiceLink) {
    processingId.value = link.id;
    router.post(
        restoreRoute([props.professionalId, link.id]).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}
</script>

<template>
    <div class="grid gap-4">
        <div v-if="links.length === 0" class="text-muted-foreground text-sm">
            Nenhum serviço vinculado ainda.
        </div>

        <ul v-else class="grid gap-2">
            <li
                v-for="link in links"
                :key="link.id"
                class="flex items-center justify-between gap-2 rounded-md border p-3"
            >
                <div>
                    <p class="text-sm font-medium">{{ link.service.name }}</p>
                    <p class="text-muted-foreground text-xs">
                        {{
                            link.deleted_at
                                ? 'Excluído'
                                : link.status === 'active'
                                  ? 'Ativo'
                                  : 'Inativo'
                        }}
                        · {{ link.duration_minutes.effective }} min{{
                            link.duration_minutes.is_inherited
                                ? ' (padrão)'
                                : ' (personalizado)'
                        }}
                        · {{ formatPrice(link.price_cents.effective)
                        }}{{
                            link.price_cents.is_inherited
                                ? ' (padrão)'
                                : ' (personalizado)'
                        }}
                    </p>
                    <p class="text-muted-foreground text-xs">
                        {{ unitScopeLabels[link.unit_scope] }}
                        <span v-if="link.compatible_units.length > 0">
                            —
                            {{
                                link.compatible_units.map(unitName).join(', ')
                            }}</span
                        >
                        <span
                            v-else-if="!link.deleted_at"
                            class="text-amber-600 dark:text-amber-400"
                        >
                            — nenhuma unidade compatível</span
                        >
                    </p>
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            :disabled="processingId === link.id"
                            :aria-label="`Ações para ${link.service.name}`"
                        >
                            <MoreHorizontal class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <template v-if="link.deleted_at">
                            <DropdownMenuItem @select="restore(link)">
                                Restaurar
                            </DropdownMenuItem>
                        </template>
                        <template v-else>
                            <DropdownMenuItem @select="openEdit(link)">
                                Editar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="link.status === 'active'"
                                @select="toggleActivate(link)"
                            >
                                Inativar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-else
                                @select="toggleActivate(link)"
                            >
                                Ativar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                variant="destructive"
                                @select="pendingRemoval = link"
                            >
                                Remover
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
            </li>
        </ul>

        <div>
            <Button
                type="button"
                variant="secondary"
                size="sm"
                @click="openCreate"
            >
                Vincular serviço
            </Button>
        </div>

        <Sheet v-model:open="sheetOpen">
            <SheetContent
                side="right"
                class="w-full gap-0 overflow-y-auto sm:max-w-lg"
            >
                <SheetHeader>
                    <SheetTitle>
                        {{
                            editingLink ? 'Editar vínculo' : 'Vincular serviço'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        Duração, preço, intervalos e unidades deste vínculo.
                    </SheetDescription>
                </SheetHeader>
                <div class="px-4 pb-6">
                    <ServiceAssignmentForm
                        v-if="sheetOpen"
                        :key="editingLink?.id ?? 'create'"
                        :mode="editingLink ? 'edit' : 'create'"
                        :professional-id="professionalId"
                        :professional-units="professionalUnits"
                        :eligible-services="eligibleServices"
                        :link="editableLink"
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
                    <DialogTitle>Remover serviço?</DialogTitle>
                    <DialogDescription>
                        Este vínculo será removido da operação, mas seu
                        histórico será preservado.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancelar</Button>
                    </DialogClose>
                    <Button variant="destructive" @click="confirmRemove"
                        >Remover</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

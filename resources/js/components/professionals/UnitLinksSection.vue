<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { MoreHorizontal } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    primary as setPrimaryRoute,
    restore as restoreRoute,
    store,
    update,
} from '@/routes/settings/professionals/units';

export type UnitLink = {
    id: string;
    unit: { id: string; name: string };
    is_primary: boolean;
    status: 'active' | 'inactive';
    starts_on: string | null;
    ends_on: string | null;
    vigency_status: 'scheduled' | 'in_effect' | 'ended';
    deleted_at: string | null;
};

export type UnitOption = { id: string; name: string };

const props = defineProps<{
    professionalId: string;
    links: UnitLink[];
    eligibleUnits: UnitOption[];
}>();

const vigencyLabels: Record<UnitLink['vigency_status'], string> = {
    scheduled: 'Agendado',
    in_effect: 'Vigente',
    ended: 'Encerrado',
};

const assignForm = useForm({ unit_id: '', starts_on: '', ends_on: '' });
const processingId = ref<string | null>(null);
const pendingRemoval = ref<UnitLink | null>(null);
const editingLink = ref<UnitLink | null>(null);
const editForm = useForm({ starts_on: '', ends_on: '' });

function assign() {
    assignForm.post(store(props.professionalId).url, {
        preserveScroll: true,
        onSuccess: () => assignForm.reset(),
    });
}

function openEdit(link: UnitLink) {
    editingLink.value = link;
    editForm.starts_on = link.starts_on ?? '';
    editForm.ends_on = link.ends_on ?? '';
}

function submitEdit() {
    if (!editingLink.value) {
        return;
    }

    editForm.put(update([props.professionalId, editingLink.value.id]).url, {
        preserveScroll: true,
        onSuccess: () => (editingLink.value = null),
    });
}

function setPrimary(link: UnitLink) {
    processingId.value = link.id;
    router.patch(
        setPrimaryRoute([props.professionalId, link.id]).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function toggleActivate(link: UnitLink) {
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

function restore(link: UnitLink) {
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
        <div v-if="links.length === 0" class="text-sm text-muted-foreground">
            Nenhuma unidade vinculada ainda.
        </div>

        <ul v-else class="grid gap-2">
            <li
                v-for="link in links"
                :key="link.id"
                class="flex items-center justify-between gap-2 rounded-md border p-3"
            >
                <div>
                    <p class="text-sm font-medium">
                        {{ link.unit.name }}
                        <span
                            v-if="link.is_primary"
                            class="ml-1 rounded bg-primary/10 px-1.5 py-0.5 text-xs text-primary"
                            >Principal</span
                        >
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            link.deleted_at
                                ? 'Excluído'
                                : link.status === 'active'
                                  ? 'Ativo'
                                  : 'Inativo'
                        }}
                        · {{ vigencyLabels[link.vigency_status] }}
                        <span v-if="link.starts_on || link.ends_on">
                            ({{ link.starts_on ?? '—' }} a
                            {{ link.ends_on ?? '—' }})</span
                        >
                    </p>
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            :disabled="processingId === link.id"
                            :aria-label="`Ações para ${link.unit.name}`"
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
                                Editar vigência
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="!link.is_primary"
                                @select="setPrimary(link)"
                            >
                                Definir como principal
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

        <form
            v-if="eligibleUnits.length > 0"
            class="grid gap-3 rounded-md border p-3"
            @submit.prevent="assign"
        >
            <div class="grid gap-2">
                <label for="unit-select" class="text-sm font-medium"
                    >Adicionar unidade</label
                >
                <select
                    id="unit-select"
                    v-model="assignForm.unit_id"
                    class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="">Selecione uma unidade</option>
                    <option
                        v-for="option in eligibleUnits"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ option.name }}
                    </option>
                </select>
                <InputError :message="assignForm.errors.unit_id" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="unit-starts-on">Início (opcional)</Label>
                    <Input
                        id="unit-starts-on"
                        v-model="assignForm.starts_on"
                        type="date"
                    />
                    <InputError :message="assignForm.errors.starts_on" />
                </div>
                <div class="grid gap-2">
                    <Label for="unit-ends-on">Fim (opcional)</Label>
                    <Input
                        id="unit-ends-on"
                        v-model="assignForm.ends_on"
                        type="date"
                    />
                    <InputError :message="assignForm.errors.ends_on" />
                </div>
            </div>

            <div>
                <Button
                    type="submit"
                    :disabled="assignForm.processing || !assignForm.unit_id"
                >
                    Adicionar
                </Button>
            </div>
        </form>
        <p v-else class="text-sm text-muted-foreground">
            Não há unidades ativas disponíveis para adicionar.
        </p>

        <Sheet
            :open="editingLink !== null"
            @update:open="(open) => !open && (editingLink = null)"
        >
            <SheetContent side="right" class="w-full gap-0 sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>Editar vigência</SheetTitle>
                    <SheetDescription>
                        Período em que o profissional atua nesta unidade.
                    </SheetDescription>
                </SheetHeader>
                <form class="grid gap-4 px-4 pb-6" @submit.prevent="submitEdit">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="edit-unit-starts-on"
                                >Início (opcional)</Label
                            >
                            <Input
                                id="edit-unit-starts-on"
                                v-model="editForm.starts_on"
                                type="date"
                            />
                            <InputError :message="editForm.errors.starts_on" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit-unit-ends-on"
                                >Fim (opcional)</Label
                            >
                            <Input
                                id="edit-unit-ends-on"
                                v-model="editForm.ends_on"
                                type="date"
                            />
                            <InputError :message="editForm.errors.ends_on" />
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            :disabled="editForm.processing"
                            @click="editingLink = null"
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="editForm.processing">
                            Salvar
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>

        <Dialog
            :open="pendingRemoval !== null"
            @update:open="(open) => !open && (pendingRemoval = null)"
        >
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Remover unidade?</DialogTitle>
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

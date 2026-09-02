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
import {
    activate,
    deactivate,
    destroy,
    primary as setPrimaryRoute,
    restore as restoreRoute,
    store,
} from '@/routes/settings/professionals/specialties';

export type SpecialtyLink = {
    id: string;
    specialty: { id: string; name: string };
    is_primary: boolean;
    status: 'active' | 'inactive';
    deleted_at: string | null;
};

export type SpecialtyOption = { id: string; name: string };

const props = defineProps<{
    professionalId: string;
    links: SpecialtyLink[];
    eligibleSpecialties: SpecialtyOption[];
}>();

const form = useForm({ specialty_id: '' });
const processingId = ref<string | null>(null);
const pendingRemoval = ref<SpecialtyLink | null>(null);

function assign() {
    form.post(store(props.professionalId).url, {
        preserveScroll: true,
        onSuccess: () => (form.specialty_id = ''),
    });
}

function setPrimary(link: SpecialtyLink) {
    processingId.value = link.id;
    router.patch(
        setPrimaryRoute([props.professionalId, link.id]).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function toggleActivate(link: SpecialtyLink) {
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

function restore(link: SpecialtyLink) {
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
            Nenhuma especialidade vinculada ainda.
        </div>

        <ul v-else class="grid gap-2">
            <li
                v-for="link in links"
                :key="link.id"
                class="flex items-center justify-between gap-2 rounded-md border p-3"
            >
                <div>
                    <p class="text-sm font-medium">
                        {{ link.specialty.name }}
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
                    </p>
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            :disabled="processingId === link.id"
                            :aria-label="`Ações para ${link.specialty.name}`"
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
            v-if="eligibleSpecialties.length > 0"
            class="flex items-end gap-2"
            @submit.prevent="assign"
        >
            <div class="grid flex-1 gap-2">
                <label for="specialty-select" class="text-sm font-medium"
                    >Adicionar especialidade</label
                >
                <select
                    id="specialty-select"
                    v-model="form.specialty_id"
                    class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="">Selecione uma especialidade</option>
                    <option
                        v-for="option in eligibleSpecialties"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ option.name }}
                    </option>
                </select>
                <InputError :message="form.errors.specialty_id" />
            </div>
            <Button
                type="submit"
                :disabled="form.processing || !form.specialty_id"
            >
                Adicionar
            </Button>
        </form>
        <p v-else class="text-sm text-muted-foreground">
            Não há especialidades ativas disponíveis para adicionar.
        </p>

        <Dialog
            :open="pendingRemoval !== null"
            @update:open="(open) => !open && (pendingRemoval = null)"
        >
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Remover especialidade?</DialogTitle>
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

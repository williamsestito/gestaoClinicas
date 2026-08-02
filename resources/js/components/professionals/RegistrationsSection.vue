<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { MoreHorizontal, Plus } from '@lucide/vue';
import { ref } from 'vue';
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
    primary as setPrimaryRoute,
    reveal,
    restore as restoreRoute,
} from '@/routes/settings/professionals/registrations';
import type { EditableRegistration } from './RegistrationForm.vue';
import RegistrationForm from './RegistrationForm.vue';

export type RegistrationRow = {
    id: string;
    council: string;
    registration_type: string | null;
    masked_registration_number: string;
    state: string | null;
    issued_at: string | null;
    expires_at: string | null;
    validity_status: 'valid' | 'expiring_soon' | 'expired' | 'no_expiration';
    is_primary: boolean;
    status: 'active' | 'inactive';
    deleted_at: string | null;
};

const props = defineProps<{
    professionalId: string;
    registrations: RegistrationRow[];
    canViewSensitive: boolean;
}>();

const validityLabels: Record<RegistrationRow['validity_status'], string> = {
    valid: 'Válido',
    expiring_soon: 'Próximo do vencimento',
    expired: 'Vencido',
    no_expiration: 'Sem validade informada',
};

const validityClasses: Record<RegistrationRow['validity_status'], string> = {
    valid: 'text-emerald-600 dark:text-emerald-400',
    expiring_soon: 'text-amber-600 dark:text-amber-400',
    expired: 'text-destructive',
    no_expiration: 'text-muted-foreground',
};

const sheetOpen = ref(false);
const editingRegistration = ref<EditableRegistration | null>(null);
const processingId = ref<string | null>(null);
const pendingRemoval = ref<RegistrationRow | null>(null);
const revealedNumbers = ref<Record<string, string>>({});

function openCreate() {
    editingRegistration.value = null;
    sheetOpen.value = true;
}

function openEdit(registration: RegistrationRow) {
    editingRegistration.value = {
        id: registration.id,
        council: registration.council,
        registration_type: registration.registration_type,
        state: registration.state,
        issued_at: registration.issued_at,
        expires_at: registration.expires_at,
    };
    sheetOpen.value = true;
}

function onFormSuccess() {
    sheetOpen.value = false;
}

function setPrimary(registration: RegistrationRow) {
    processingId.value = registration.id;
    router.patch(
        setPrimaryRoute([props.professionalId, registration.id]).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function toggleActivate(registration: RegistrationRow) {
    processingId.value = registration.id;
    const routeFn = registration.status === 'active' ? deactivate : activate;
    router.patch(
        routeFn([props.professionalId, registration.id]).url,
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

function restore(registration: RegistrationRow) {
    processingId.value = registration.id;
    router.post(
        restoreRoute([props.professionalId, registration.id]).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

async function revealNumber(registration: RegistrationRow) {
    const response = await fetch(
        reveal([props.professionalId, registration.id]).url,
        { headers: { Accept: 'application/json' } },
    );

    if (!response.ok) {
        return;
    }

    const data = (await response.json()) as { registration_number: string };
    revealedNumbers.value = {
        ...revealedNumbers.value,
        [registration.id]: data.registration_number,
    };
}
</script>

<template>
    <div class="grid gap-4">
        <div
            v-if="registrations.length === 0"
            class="text-muted-foreground text-sm"
        >
            Nenhum registro profissional cadastrado ainda.
        </div>

        <ul v-else class="grid gap-2">
            <li
                v-for="registration in registrations"
                :key="registration.id"
                class="flex items-center justify-between gap-2 rounded-md border p-3"
            >
                <div>
                    <p class="text-sm font-medium">
                        {{ registration.council }}
                        <span v-if="registration.state"
                            >/ {{ registration.state }}</span
                        >
                        <span
                            v-if="registration.is_primary"
                            class="bg-primary/10 text-primary ml-1 rounded px-1.5 py-0.5 text-xs"
                            >Principal</span
                        >
                    </p>
                    <p class="text-muted-foreground text-sm">
                        {{
                            revealedNumbers[registration.id] ??
                            registration.masked_registration_number
                        }}
                        <button
                            v-if="
                                canViewSensitive &&
                                !revealedNumbers[registration.id]
                            "
                            type="button"
                            class="text-primary ml-1 text-xs underline"
                            @click="revealNumber(registration)"
                        >
                            Ver número completo
                        </button>
                    </p>
                    <p
                        class="text-xs"
                        :class="validityClasses[registration.validity_status]"
                    >
                        {{ validityLabels[registration.validity_status] }}
                        <span v-if="registration.expires_at">
                            ({{ registration.expires_at }})</span
                        >
                    </p>
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            :disabled="processingId === registration.id"
                            :aria-label="`Ações para o registro ${registration.council}`"
                        >
                            <MoreHorizontal class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <template v-if="registration.deleted_at">
                            <DropdownMenuItem @select="restore(registration)">
                                Restaurar
                            </DropdownMenuItem>
                        </template>
                        <template v-else>
                            <DropdownMenuItem @select="openEdit(registration)">
                                Editar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="!registration.is_primary"
                                @select="setPrimary(registration)"
                            >
                                Definir como principal
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="registration.status === 'active'"
                                @select="toggleActivate(registration)"
                            >
                                Inativar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-else
                                @select="toggleActivate(registration)"
                            >
                                Ativar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                variant="destructive"
                                @select="pendingRemoval = registration"
                            >
                                Excluir
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
                <Plus class="size-4" />
                Novo registro
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
                            editingRegistration
                                ? 'Editar registro'
                                : 'Novo registro profissional'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        Conselho, número e vigência do registro profissional.
                    </SheetDescription>
                </SheetHeader>
                <div class="px-4 pb-6">
                    <RegistrationForm
                        v-if="sheetOpen"
                        :key="editingRegistration?.id ?? 'create'"
                        :mode="editingRegistration ? 'edit' : 'create'"
                        :professional-id="professionalId"
                        :registration="editingRegistration ?? undefined"
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
                    <DialogTitle>Excluir registro profissional?</DialogTitle>
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
                        >Excluir</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

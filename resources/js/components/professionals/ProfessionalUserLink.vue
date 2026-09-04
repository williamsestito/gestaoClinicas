<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy as unlinkRoute,
    password as passwordRoute,
    update as linkRoute,
} from '@/routes/settings/professionals/user';
import type { EligibleUser } from './ProfessionalForm.vue';

const props = defineProps<{
    professionalId: string;
    linkedUser: { id: number; name: string } | null;
    eligibleUsers: EligibleUser[];
}>();

const form = useForm({ user_id: undefined as number | undefined });
const unlinkDialogOpen = ref(false);
const unlinking = ref(false);

const passwordForm = useForm({ password: '', password_confirmation: '' });

function link() {
    if (!form.user_id) {
        return;
    }

    form.put(linkRoute(props.professionalId).url, {
        preserveScroll: true,
    });
}

function resetPassword() {
    passwordForm.put(passwordRoute(props.professionalId).url, {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}

function confirmUnlink() {
    unlinking.value = true;
    router.delete(unlinkRoute(props.professionalId).url, {
        preserveScroll: true,
        onFinish: () => {
            unlinking.value = false;
            unlinkDialogOpen.value = false;
        },
    });
}
</script>

<template>
    <div class="grid gap-3">
        <div
            v-if="linkedUser"
            class="flex items-center justify-between gap-2 rounded-md border p-3"
        >
            <div>
                <p class="text-sm font-medium">{{ linkedUser.name }}</p>
                <p class="text-muted-foreground text-xs">
                    Vincular ou remover o usuário não altera papéis, permissões
                    ou o acesso dele à clínica.
                </p>
            </div>
            <Button
                type="button"
                variant="secondary"
                size="sm"
                @click="unlinkDialogOpen = true"
            >
                Remover vínculo
            </Button>
        </div>

        <form
            v-if="linkedUser"
            class="grid gap-3 rounded-md border p-3"
            @submit.prevent="resetPassword"
        >
            <p class="text-sm font-medium">Redefinir senha de acesso</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="reset-password">Nova senha</Label>
                    <Input
                        id="reset-password"
                        v-model="passwordForm.password"
                        type="password"
                        autocomplete="new-password"
                    />
                    <InputError :message="passwordForm.errors.password" />
                </div>
                <div class="grid gap-2">
                    <Label for="reset-password-confirmation"
                        >Confirmar nova senha</Label
                    >
                    <Input
                        id="reset-password-confirmation"
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                    />
                    <InputError
                        :message="passwordForm.errors.password_confirmation"
                    />
                </div>
            </div>
            <div>
                <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    :disabled="passwordForm.processing"
                >
                    Redefinir senha
                </Button>
            </div>
        </form>

        <form v-else class="flex items-end gap-2" @submit.prevent="link">
            <div class="grid flex-1 gap-2">
                <label for="link-user" class="text-sm font-medium"
                    >Vincular a um usuário existente</label
                >
                <select
                    id="link-user"
                    v-model="form.user_id"
                    class="border-input shadow-xs focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 py-1 text-sm outline-none focus-visible:ring-[3px]"
                >
                    <option :value="undefined">Selecione um usuário</option>
                    <option
                        v-for="eligibleUser in eligibleUsers"
                        :key="eligibleUser.id"
                        :value="eligibleUser.id"
                    >
                        {{ eligibleUser.name }} ({{ eligibleUser.email }})
                    </option>
                </select>
                <InputError :message="form.errors.user_id" />
            </div>
            <Button type="submit" :disabled="form.processing || !form.user_id">
                Vincular
            </Button>
        </form>

        <Dialog v-model:open="unlinkDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Remover vínculo com o usuário?</DialogTitle>
                    <DialogDescription>
                        O usuário não será excluído nem desativado — apenas
                        deixará de estar associado a este profissional.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancelar</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        :disabled="unlinking"
                        @click="confirmUnlink"
                    >
                        Remover vínculo
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

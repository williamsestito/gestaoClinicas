<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/settings/roles';

export type PermissionOption = { key: string; label: string; group: string };

export type EditableRole = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    is_system: boolean;
    permissions: { key: string }[];
};

const props = defineProps<{
    mode: 'create' | 'edit';
    role?: EditableRole;
    permissionGroups: Record<string, PermissionOption[]>;
    readOnly?: boolean;
}>();

const emit = defineEmits<{ success: []; cancel: [] }>();

const form = useForm({
    name: props.role?.name ?? '',
    description: props.role?.description ?? '',
    permissions:
        props.role?.permissions.map((permission) => permission.key) ?? [],
});

function togglePermission(key: string) {
    const index = form.permissions.indexOf(key);

    if (index === -1) {
        form.permissions.push(key);
    } else {
        form.permissions.splice(index, 1);
    }
}

function submit() {
    if (props.mode === 'create') {
        form.post(store().url, { onSuccess: () => emit('success') });

        return;
    }

    if (props.role) {
        form.put(update(props.role.id).url, {
            onSuccess: () => emit('success'),
        });
    }
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="role-name">Nome do papel</Label>
            <Input
                id="role-name"
                v-model="form.name"
                :disabled="readOnly"
                autofocus
            />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="role-description">Descrição (opcional)</Label>
            <Input
                id="role-description"
                v-model="form.description"
                :disabled="readOnly"
            />
            <InputError :message="form.errors.description" />
        </div>

        <div v-if="!readOnly" class="space-y-4">
            <p class="text-sm font-medium">Permissões</p>
            <div
                v-for="(options, group) in permissionGroups"
                :key="group"
                class="space-y-2"
            >
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ group }}
                </p>
                <div class="grid gap-2 sm:grid-cols-2">
                    <Label
                        v-for="option in options"
                        :key="option.key"
                        class="flex items-center gap-2 font-normal"
                    >
                        <Checkbox
                            :model-value="form.permissions.includes(option.key)"
                            @update:model-value="togglePermission(option.key)"
                        />
                        {{ option.label }}
                    </Label>
                </div>
            </div>
            <InputError :message="form.errors.permissions" />
        </div>
        <p v-else class="text-sm text-muted-foreground">
            As permissões do papel "Proprietário" não podem ser alteradas — ele
            sempre tem acesso total à clínica.
        </p>

        <div class="flex justify-end gap-2">
            <Button type="button" variant="secondary" @click="emit('cancel')">
                Cancelar
            </Button>
            <Button v-if="!readOnly" type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Salvar
            </Button>
        </div>
    </form>
</template>

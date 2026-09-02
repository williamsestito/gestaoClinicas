<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { invite, update } from '@/routes/settings/users';

export type RoleOption = { id: string; name: string; is_system: boolean };
export type UnitOption = { id: string; name: string };

export type EditableMembership = {
    id: string;
    admin_note: string | null;
    role: { id: string } | null;
    user: { name: string; email: string };
    unit_memberships: { unit_id: string; is_primary: boolean }[];
};

const props = defineProps<{
    mode: 'invite' | 'edit';
    membership?: EditableMembership;
    roles: RoleOption[];
    units: UnitOption[];
}>();

const emit = defineEmits<{ success: []; cancel: [] }>();

const initialUnitIds =
    props.membership?.unit_memberships.map((um) => um.unit_id) ?? [];
const initialPrimary =
    props.membership?.unit_memberships.find((um) => um.is_primary)?.unit_id ??
    null;

const inviteForm = useForm({
    email: '',
    role_id: null as string | null,
    unit_ids: [] as string[],
});

const editForm = useForm({
    role_id: props.membership?.role?.id ?? (null as string | null),
    admin_note: props.membership?.admin_note ?? '',
    unit_ids: initialUnitIds,
    primary_unit_id: initialPrimary as string | null,
});

const form = props.mode === 'invite' ? inviteForm : editForm;

function toggleUnit(unitId: string) {
    const list = form.unit_ids;
    const index = list.indexOf(unitId);

    if (index === -1) {
        list.push(unitId);
    } else {
        list.splice(index, 1);

        if (editForm.primary_unit_id === unitId) {
            editForm.primary_unit_id = null;
        }
    }
}

function submit() {
    if (props.mode === 'invite') {
        inviteForm.post(invite().url, { onSuccess: () => emit('success') });

        return;
    }

    if (props.membership) {
        editForm.put(update(props.membership.id).url, {
            onSuccess: () => emit('success'),
        });
    }
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div v-if="mode === 'invite'" class="grid gap-2">
            <Label for="invite-email">E-mail</Label>
            <Input
                id="invite-email"
                v-model="inviteForm.email"
                type="email"
                autofocus
                placeholder="email@example.com"
            />
            <InputError :message="inviteForm.errors.email" />
        </div>

        <div v-else class="grid gap-1">
            <p class="text-sm font-medium">{{ membership?.user.name }}</p>
            <p class="text-sm text-muted-foreground">
                {{ membership?.user.email }}
            </p>
        </div>

        <div class="grid gap-2">
            <Label for="role">Papel</Label>
            <Select v-model="form.role_id">
                <SelectTrigger id="role" class="w-full">
                    <SelectValue placeholder="Sem papel atribuído" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="role in roles"
                        :key="role.id"
                        :value="role.id"
                    >
                        {{ role.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="form.errors.role_id" />
        </div>

        <div class="space-y-2">
            <p class="text-sm font-medium">Unidades permitidas</p>
            <div class="grid gap-2 sm:grid-cols-2">
                <Label
                    v-for="unit in units"
                    :key="unit.id"
                    class="flex items-center gap-2 font-normal"
                >
                    <Checkbox
                        :model-value="form.unit_ids.includes(unit.id)"
                        @update:model-value="toggleUnit(unit.id)"
                    />
                    {{ unit.name }}
                </Label>
            </div>
            <InputError :message="form.errors.unit_ids" />
        </div>

        <div v-if="mode === 'edit'" class="grid gap-2">
            <Label for="primary-unit">Unidade principal</Label>
            <Select v-model="editForm.primary_unit_id">
                <SelectTrigger id="primary-unit" class="w-full">
                    <SelectValue placeholder="Nenhuma definida" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="unit in units.filter((u) =>
                            editForm.unit_ids.includes(u.id),
                        )"
                        :key="unit.id"
                        :value="unit.id"
                    >
                        {{ unit.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div v-if="mode === 'edit'" class="grid gap-2">
            <Label for="admin-note">Observação administrativa (opcional)</Label>
            <Input id="admin-note" v-model="editForm.admin_note" />
            <InputError :message="editForm.errors.admin_note" />
        </div>

        <div class="flex justify-end gap-2">
            <Button type="button" variant="secondary" @click="emit('cancel')">
                Cancelar
            </Button>
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ mode === 'invite' ? 'Enviar convite' : 'Salvar' }}
            </Button>
        </div>
    </form>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/settings/resources';

export type EditableResource = {
    id: string;
    unit_id: string;
    name: string;
    type: string | null;
};

type UnitOption = { id: string; name: string };

const props = withDefaults(
    defineProps<{
        mode: 'create' | 'edit';
        resource?: EditableResource;
        units: UnitOption[];
    }>(),
    {
        resource: undefined,
    },
);

const emit = defineEmits<{
    success: [];
    cancel: [];
}>();

const form = useForm({
    unit_id: props.resource?.unit_id ?? '',
    name: props.resource?.name ?? '',
    type: props.resource?.type ?? '',
});

function submit() {
    if (props.mode === 'create') {
        form.post(store().url, { onSuccess: () => emit('success') });

        return;
    }

    if (props.resource) {
        form.put(update(props.resource.id).url, {
            onSuccess: () => emit('success'),
        });
    }
}
</script>

<template>
    <form class="flex flex-col gap-4" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="resource-unit">Unidade</Label>
            <select
                id="resource-unit"
                v-model="form.unit_id"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="" disabled>Selecione</option>
                <option v-for="unit in units" :key="unit.id" :value="unit.id">
                    {{ unit.name }}
                </option>
            </select>
            <InputError :message="form.errors.unit_id" />
        </div>

        <div class="grid gap-2">
            <Label for="resource-name">Nome</Label>
            <Input
                id="resource-name"
                v-model="form.name"
                autofocus
                autocomplete="off"
                placeholder="Ex.: Sala 1, Aparelho de ultrassom"
            />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="resource-type">Tipo (opcional)</Label>
            <Input
                id="resource-type"
                v-model="form.type"
                autocomplete="off"
                placeholder="Ex.: Sala, Equipamento"
            />
            <InputError :message="form.errors.type" />
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
            <Button
                type="button"
                variant="secondary"
                :disabled="form.processing"
                @click="emit('cancel')"
            >
                Cancelar
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ mode === 'create' ? 'Criar recurso' : 'Salvar alterações' }}
            </Button>
        </div>
    </form>
</template>

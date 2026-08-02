<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/settings/specialties';

export type EditableSpecialty = {
    id: string;
    name: string;
    code: string | null;
    description: string | null;
    display_order: number;
};

const props = withDefaults(
    defineProps<{
        mode: 'create' | 'edit';
        specialty?: EditableSpecialty;
    }>(),
    {
        specialty: undefined,
    },
);

const emit = defineEmits<{
    success: [];
    cancel: [];
}>();

const form = useForm({
    name: props.specialty?.name ?? '',
    code: props.specialty?.code ?? '',
    description: props.specialty?.description ?? '',
    display_order: props.specialty?.display_order ?? 0,
});

function submit() {
    if (props.mode === 'create') {
        form.post(store().url, { onSuccess: () => emit('success') });

        return;
    }

    if (props.specialty) {
        form.put(update(props.specialty.id).url, {
            onSuccess: () => emit('success'),
        });
    }
}
</script>

<template>
    <form class="flex flex-col gap-4" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="specialty-name">Nome</Label>
            <Input
                id="specialty-name"
                v-model="form.name"
                autofocus
                autocomplete="off"
            />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="specialty-code">Código (opcional)</Label>
            <Input
                id="specialty-code"
                v-model="form.code"
                autocomplete="off"
                placeholder="Ex.: CARDIO"
            />
            <InputError :message="form.errors.code" />
        </div>

        <div class="grid gap-2">
            <Label for="specialty-description">Descrição (opcional)</Label>
            <Textarea
                id="specialty-description"
                v-model="form.description"
                rows="3"
            />
            <InputError :message="form.errors.description" />
        </div>

        <div class="grid gap-2">
            <Label for="specialty-order">Ordem de exibição</Label>
            <Input
                id="specialty-order"
                v-model.number="form.display_order"
                type="number"
                min="0"
                max="9999"
                class="max-w-32"
            />
            <InputError :message="form.errors.display_order" />
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
                {{
                    mode === 'create'
                        ? 'Criar especialidade'
                        : 'Salvar alterações'
                }}
            </Button>
        </div>
    </form>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/settings/professionals/working-hours';

const WEEKDAY_OPTIONS = [
    { value: 1, label: 'Segunda-feira' },
    { value: 2, label: 'Terça-feira' },
    { value: 3, label: 'Quarta-feira' },
    { value: 4, label: 'Quinta-feira' },
    { value: 5, label: 'Sexta-feira' },
    { value: 6, label: 'Sábado' },
    { value: 0, label: 'Domingo' },
];

export type EditableWorkingHour = {
    id: string;
    weekday: number;
    starts_at: string;
    ends_at: string;
    effective_from: string | null;
    effective_until: string | null;
};

const props = withDefaults(
    defineProps<{
        mode: 'create' | 'edit';
        professionalId: string;
        professionalUnitId: string;
        defaultWeekday?: number;
        workingHour?: EditableWorkingHour;
    }>(),
    {
        defaultWeekday: 1,
        workingHour: undefined,
    },
);

const emit = defineEmits<{
    success: [];
    cancel: [];
}>();

const form = useForm({
    weekday: props.workingHour?.weekday ?? props.defaultWeekday,
    starts_at: props.workingHour?.starts_at ?? '',
    ends_at: props.workingHour?.ends_at ?? '',
    effective_from: props.workingHour?.effective_from ?? '',
    effective_until: props.workingHour?.effective_until ?? '',
});

function submit() {
    if (props.mode === 'create') {
        form.post(store([props.professionalId, props.professionalUnitId]).url, {
            preserveScroll: true,
            onSuccess: () => emit('success'),
        });

        return;
    }

    if (props.workingHour) {
        form.put(update([props.professionalId, props.workingHour.id]).url, {
            preserveScroll: true,
            onSuccess: () => emit('success'),
        });
    }
}
</script>

<template>
    <form class="grid gap-4" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="working-hour-weekday">Dia da semana</Label>
            <select
                id="working-hour-weekday"
                v-model.number="form.weekday"
                class="border-input shadow-xs focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 py-1 text-sm outline-none focus-visible:ring-[3px]"
            >
                <option
                    v-for="option in WEEKDAY_OPTIONS"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <InputError :message="form.errors.weekday" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="grid gap-2">
                <Label for="working-hour-starts">Início</Label>
                <Input
                    id="working-hour-starts"
                    v-model="form.starts_at"
                    type="time"
                />
                <InputError :message="form.errors.starts_at" />
            </div>
            <div class="grid gap-2">
                <Label for="working-hour-ends">Fim</Label>
                <Input
                    id="working-hour-ends"
                    v-model="form.ends_at"
                    type="time"
                />
                <InputError :message="form.errors.ends_at" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="grid gap-2">
                <Label for="working-hour-effective-from"
                    >Vigência inicial (opcional)</Label
                >
                <Input
                    id="working-hour-effective-from"
                    v-model="form.effective_from"
                    type="date"
                />
                <InputError :message="form.errors.effective_from" />
            </div>
            <div class="grid gap-2">
                <Label for="working-hour-effective-until"
                    >Vigência final (opcional)</Label
                >
                <Input
                    id="working-hour-effective-until"
                    v-model="form.effective_until"
                    type="date"
                />
                <InputError :message="form.errors.effective_until" />
            </div>
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
                        ? 'Adicionar intervalo'
                        : 'Salvar alterações'
                }}
            </Button>
        </div>
    </form>
</template>

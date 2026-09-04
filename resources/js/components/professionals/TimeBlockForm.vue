<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/settings/professionals/time-blocks';

const TYPE_OPTIONS = [
    { value: 'vacation', label: 'Férias' },
    { value: 'day_off', label: 'Folga' },
    { value: 'absence', label: 'Ausência' },
    { value: 'administrative_block', label: 'Bloqueio administrativo' },
    { value: 'external_event', label: 'Evento externo' },
    { value: 'partial_unavailability', label: 'Indisponibilidade parcial' },
];

export type UnitOption = { id: string; name: string };

export type EditableTimeBlock = {
    id: string;
    type: string;
    scope: 'all_units' | 'specific_unit';
    unit: { id: string; name: string } | null;
    timezone: string;
    starts_at: string;
    ends_at: string;
    is_all_day: boolean;
    reason: string | null;
    internal_notes: string | null;
};

const props = withDefaults(
    defineProps<{
        mode: 'create' | 'edit';
        professionalId: string;
        eligibleUnits: UnitOption[];
        timeBlock?: EditableTimeBlock;
    }>(),
    {
        timeBlock: undefined,
    },
);

const emit = defineEmits<{
    success: [];
    cancel: [];
}>();

function toLocalDateTimeParts(isoValue: string, timezone: string) {
    const date = new Date(isoValue);
    const formatter = new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
    const parts = formatter.formatToParts(date);
    const lookup = (type: string) =>
        parts.find((part) => part.type === type)?.value ?? '';

    return {
        date: `${lookup('year')}-${lookup('month')}-${lookup('day')}`,
        time: `${lookup('hour')}:${lookup('minute')}`,
    };
}

const initialStarts = props.timeBlock
    ? toLocalDateTimeParts(props.timeBlock.starts_at, props.timeBlock.timezone)
    : null;
const initialEnds = props.timeBlock
    ? toLocalDateTimeParts(props.timeBlock.ends_at, props.timeBlock.timezone)
    : null;

const form = useForm({
    type: props.timeBlock?.type ?? 'vacation',
    scope: props.timeBlock?.scope ?? 'all_units',
    unit_id: props.timeBlock?.unit?.id ?? '',
    is_all_day: props.timeBlock?.is_all_day ?? true,
    starts_date: initialStarts?.date ?? '',
    starts_time: props.timeBlock?.is_all_day ? '' : (initialStarts?.time ?? ''),
    ends_date: initialEnds?.date ?? '',
    ends_time: props.timeBlock?.is_all_day ? '' : (initialEnds?.time ?? ''),
    reason: props.timeBlock?.reason ?? '',
    internal_notes: props.timeBlock?.internal_notes ?? '',
});

function submit() {
    if (props.mode === 'create') {
        form.post(store(props.professionalId).url, {
            preserveScroll: true,
            onSuccess: () => emit('success'),
        });

        return;
    }

    if (props.timeBlock) {
        form.put(update([props.professionalId, props.timeBlock.id]).url, {
            preserveScroll: true,
            onSuccess: () => emit('success'),
        });
    }
}
</script>

<template>
    <form class="grid gap-4" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="time-block-type">Tipo</Label>
            <select
                id="time-block-type"
                v-model="form.type"
                class="border-input shadow-xs focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 py-1 text-sm outline-none focus-visible:ring-[3px]"
            >
                <option
                    v-for="option in TYPE_OPTIONS"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <InputError :message="form.errors.type" />
        </div>

        <div class="grid gap-2">
            <Label>Escopo</Label>
            <div class="grid gap-1">
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.scope"
                        type="radio"
                        value="all_units"
                    />
                    Todas as unidades
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.scope"
                        type="radio"
                        value="specific_unit"
                    />
                    Unidade específica
                </label>
            </div>
            <InputError :message="form.errors.scope" />
        </div>

        <div v-if="form.scope === 'specific_unit'" class="grid gap-2">
            <Label for="time-block-unit">Unidade</Label>
            <select
                id="time-block-unit"
                v-model="form.unit_id"
                class="border-input shadow-xs focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 py-1 text-sm outline-none focus-visible:ring-[3px]"
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
            <InputError :message="form.errors.unit_id" />
        </div>
        <p v-else class="text-muted-foreground text-xs">
            Datas e horários para todas as unidades usam o fuso da unidade
            principal do profissional.
        </p>

        <label class="flex items-center gap-2 text-sm">
            <input v-model="form.is_all_day" type="checkbox" />
            Dia inteiro
        </label>

        <div class="grid grid-cols-2 gap-4">
            <div class="grid gap-2">
                <Label for="time-block-starts-date">Data inicial</Label>
                <Input
                    id="time-block-starts-date"
                    v-model="form.starts_date"
                    type="date"
                />
                <InputError :message="form.errors.starts_date" />
            </div>
            <div v-if="!form.is_all_day" class="grid gap-2">
                <Label for="time-block-starts-time">Hora inicial</Label>
                <Input
                    id="time-block-starts-time"
                    v-model="form.starts_time"
                    type="time"
                />
                <InputError :message="form.errors.starts_time" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="grid gap-2">
                <Label for="time-block-ends-date">Data final</Label>
                <Input
                    id="time-block-ends-date"
                    v-model="form.ends_date"
                    type="date"
                />
                <InputError :message="form.errors.ends_date" />
            </div>
            <div v-if="!form.is_all_day" class="grid gap-2">
                <Label for="time-block-ends-time">Hora final</Label>
                <Input
                    id="time-block-ends-time"
                    v-model="form.ends_time"
                    type="time"
                />
                <InputError :message="form.errors.ends_time" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="time-block-reason">Motivo (opcional)</Label>
            <Input
                id="time-block-reason"
                v-model="form.reason"
                maxlength="255"
            />
            <InputError :message="form.errors.reason" />
        </div>

        <div class="grid gap-2">
            <Label for="time-block-internal-notes"
                >Observações internas (opcional)</Label
            >
            <Textarea
                id="time-block-internal-notes"
                v-model="form.internal_notes"
                maxlength="2000"
                rows="3"
            />
            <InputError :message="form.errors.internal_notes" />
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
                {{ mode === 'create' ? 'Cadastrar' : 'Salvar alterações' }}
            </Button>
        </div>
    </form>
</template>

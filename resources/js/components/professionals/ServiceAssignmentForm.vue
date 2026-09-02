<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/settings/professionals/services';

export type ServiceOption = { id: string; name: string };
export type ProfessionalUnitOption = { id: string; name: string };

export type EditableServiceLink = {
    id: string;
    custom_duration_minutes: number | null;
    custom_price: number | null;
    custom_buffer_before_minutes: number | null;
    custom_buffer_after_minutes: number | null;
    unit_scope: 'all_compatible_units' | 'selected_units' | 'none';
    unit_ids: string[];
    defaults: {
        duration_minutes: number;
        price: number | null;
        buffer_before_minutes: number;
        buffer_after_minutes: number;
    };
};

const props = withDefaults(
    defineProps<{
        mode: 'create' | 'edit';
        professionalId: string;
        professionalUnits: ProfessionalUnitOption[];
        eligibleServices?: ServiceOption[];
        link?: EditableServiceLink;
    }>(),
    {
        eligibleServices: () => [],
        link: undefined,
    },
);

const emit = defineEmits<{
    success: [];
    cancel: [];
}>();

const form = useForm({
    service_id: '',
    custom_duration_minutes: props.link?.custom_duration_minutes ?? null,
    custom_price: props.link?.custom_price ?? null,
    custom_buffer_before_minutes:
        props.link?.custom_buffer_before_minutes ?? null,
    custom_buffer_after_minutes:
        props.link?.custom_buffer_after_minutes ?? null,
    unit_scope: props.link?.unit_scope ?? 'all_compatible_units',
    unit_ids: [...(props.link?.unit_ids ?? [])],
});

function toggleUnit(unitId: string, checked: boolean) {
    if (checked) {
        form.unit_ids = [...form.unit_ids, unitId];

        return;
    }

    form.unit_ids = form.unit_ids.filter((id) => id !== unitId);
}

function submit() {
    if (props.mode === 'create') {
        form.post(store(props.professionalId).url, {
            preserveScroll: true,
            onSuccess: () => emit('success'),
        });

        return;
    }

    if (props.link) {
        form.put(update([props.professionalId, props.link.id]).url, {
            preserveScroll: true,
            onSuccess: () => emit('success'),
        });
    }
}
</script>

<template>
    <form class="grid gap-4" @submit.prevent="submit">
        <div v-if="mode === 'create'" class="grid gap-2">
            <Label for="service-assignment-service">Serviço</Label>
            <select
                id="service-assignment-service"
                v-model="form.service_id"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="">Selecione um serviço</option>
                <option
                    v-for="option in eligibleServices"
                    :key="option.id"
                    :value="option.id"
                >
                    {{ option.name }}
                </option>
            </select>
            <InputError :message="form.errors.service_id" />
        </div>

        <div class="grid gap-2">
            <label class="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    :checked="form.custom_duration_minutes === null"
                    @change="
                        form.custom_duration_minutes = (
                            $event.target as HTMLInputElement
                        ).checked
                            ? null
                            : (link?.defaults.duration_minutes ?? 30)
                    "
                />
                Usar duração padrão do serviço
                <span v-if="link"
                    >({{ link.defaults.duration_minutes }} min)</span
                >
            </label>
            <Input
                v-if="form.custom_duration_minutes !== null"
                v-model.number="form.custom_duration_minutes"
                type="number"
                min="1"
                max="1440"
                aria-label="Duração personalizada em minutos"
            />
            <InputError :message="form.errors.custom_duration_minutes" />
        </div>

        <div class="grid gap-2">
            <label class="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    :checked="form.custom_price === null"
                    @change="
                        form.custom_price = ($event.target as HTMLInputElement)
                            .checked
                            ? null
                            : (link?.defaults.price ?? 0)
                    "
                />
                Usar preço padrão do serviço
            </label>
            <Input
                v-if="form.custom_price !== null"
                v-model.number="form.custom_price"
                type="number"
                min="0"
                step="0.01"
                aria-label="Preço personalizado em reais"
            />
            <InputError :message="form.errors.custom_price" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="grid gap-2">
                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        :checked="form.custom_buffer_before_minutes === null"
                        @change="
                            form.custom_buffer_before_minutes = (
                                $event.target as HTMLInputElement
                            ).checked
                                ? null
                                : (link?.defaults.buffer_before_minutes ?? 0)
                        "
                    />
                    Intervalo antes padrão
                </label>
                <Input
                    v-if="form.custom_buffer_before_minutes !== null"
                    v-model.number="form.custom_buffer_before_minutes"
                    type="number"
                    min="0"
                    max="1440"
                    aria-label="Intervalo antes personalizado em minutos"
                />
                <InputError
                    :message="form.errors.custom_buffer_before_minutes"
                />
            </div>
            <div class="grid gap-2">
                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        :checked="form.custom_buffer_after_minutes === null"
                        @change="
                            form.custom_buffer_after_minutes = (
                                $event.target as HTMLInputElement
                            ).checked
                                ? null
                                : (link?.defaults.buffer_after_minutes ?? 0)
                        "
                    />
                    Intervalo depois padrão
                </label>
                <Input
                    v-if="form.custom_buffer_after_minutes !== null"
                    v-model.number="form.custom_buffer_after_minutes"
                    type="number"
                    min="0"
                    max="1440"
                    aria-label="Intervalo depois personalizado em minutos"
                />
                <InputError
                    :message="form.errors.custom_buffer_after_minutes"
                />
            </div>
        </div>

        <div class="grid gap-2">
            <Label>Unidades em que o vínculo se aplica</Label>
            <div class="grid gap-1">
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.unit_scope"
                        type="radio"
                        value="all_compatible_units"
                    />
                    Todas as unidades compatíveis
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.unit_scope"
                        type="radio"
                        value="selected_units"
                    />
                    Unidades selecionadas
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.unit_scope"
                        type="radio"
                        value="none"
                    />
                    Nenhuma unidade
                </label>
            </div>
            <InputError :message="form.errors.unit_scope" />

            <div
                v-if="form.unit_scope === 'selected_units'"
                class="grid gap-1 rounded-md border p-2"
            >
                <label
                    v-for="unit in professionalUnits"
                    :key="unit.id"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        :checked="form.unit_ids.includes(unit.id)"
                        @change="
                            toggleUnit(
                                unit.id,
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    {{ unit.name }}
                </label>
                <p
                    v-if="professionalUnits.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    O profissional não possui unidades de atuação ativas.
                </p>
                <InputError :message="form.errors.unit_ids" />
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
            <Button
                type="submit"
                :disabled="
                    form.processing || (mode === 'create' && !form.service_id)
                "
            >
                {{
                    mode === 'create' ? 'Vincular serviço' : 'Salvar alterações'
                }}
            </Button>
        </div>
    </form>
</template>

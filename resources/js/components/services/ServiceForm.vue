<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/settings/services';

export type ServiceOption = { id: string; name: string };
export type UnitOption = { id: string; name: string; is_active: boolean };

export type EditableService = {
    id: string;
    name: string;
    code: string;
    description: string | null;
    default_duration_minutes: number;
    buffer_before_minutes: number;
    buffer_after_minutes: number;
    default_price: number | null;
    cost: number | null;
    margin_percentage: number | null;
    max_discount_percentage: number | null;
    color: string | null;
    is_public: boolean;
    requires_manual_confirmation: boolean;
    internal_notes: string | null;
    unit_availability_scope: 'all_units' | 'selected_units' | 'none';
    specialty_ids: string[];
    unit_ids: string[];
};

const props = withDefaults(
    defineProps<{
        mode: 'create' | 'edit';
        service?: EditableService;
        specialties: ServiceOption[];
        units: UnitOption[];
    }>(),
    {
        service: undefined,
    },
);

const emit = defineEmits<{
    success: [];
    cancel: [];
}>();

const form = useForm({
    name: props.service?.name ?? '',
    code: props.service?.code ?? '',
    description: props.service?.description ?? '',
    default_duration_minutes: props.service?.default_duration_minutes ?? 30,
    buffer_before_minutes: props.service?.buffer_before_minutes ?? 0,
    buffer_after_minutes: props.service?.buffer_after_minutes ?? 0,
    default_price:
        props.service?.default_price ?? (undefined as number | undefined),
    cost: props.service?.cost ?? (undefined as number | undefined),
    margin_percentage:
        props.service?.margin_percentage ?? (undefined as number | undefined),
    max_discount_percentage:
        props.service?.max_discount_percentage ??
        (undefined as number | undefined),
    color: props.service?.color ?? '',
    is_public: props.service?.is_public ?? false,
    requires_manual_confirmation:
        props.service?.requires_manual_confirmation ?? false,
    internal_notes: props.service?.internal_notes ?? '',
    unit_availability_scope:
        props.service?.unit_availability_scope ?? 'all_units',
    specialty_ids: props.service?.specialty_ids ?? ([] as string[]),
    unit_ids: props.service?.unit_ids ?? ([] as string[]),
});

const showUnitSelection = computed(
    () => form.unit_availability_scope === 'selected_units',
);

function toggleSpecialty(id: string, checked: boolean) {
    if (checked) {
        form.specialty_ids = [...form.specialty_ids, id];
    } else {
        form.specialty_ids = form.specialty_ids.filter((s) => s !== id);
    }
}

function toggleUnit(id: string, checked: boolean) {
    if (checked) {
        form.unit_ids = [...form.unit_ids, id];
    } else {
        form.unit_ids = form.unit_ids.filter((u) => u !== id);
    }
}

function submit() {
    if (props.mode === 'create') {
        form.post(store().url, { onSuccess: () => emit('success') });

        return;
    }

    if (props.service) {
        form.put(update(props.service.id).url, {
            onSuccess: () => emit('success'),
        });
    }
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2 sm:col-span-2">
                <Label for="service-name">Nome</Label>
                <Input id="service-name" v-model="form.name" autofocus />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="service-code">Código</Label>
                <Input
                    id="service-code"
                    v-model="form.code"
                    placeholder="Ex.: CONS-01"
                />
                <InputError :message="form.errors.code" />
            </div>

            <div class="grid gap-2">
                <Label for="service-color">Cor (opcional)</Label>
                <Input
                    id="service-color"
                    v-model="form.color"
                    type="text"
                    placeholder="#2563EB"
                />
                <InputError :message="form.errors.color" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="service-description">Descrição (opcional)</Label>
                <Textarea
                    id="service-description"
                    v-model="form.description"
                    rows="3"
                />
                <InputError :message="form.errors.description" />
            </div>
        </div>

        <Separator />

        <div class="grid gap-4">
            <h3 class="text-sm font-medium">Duração e intervalos</h3>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="service-duration">Duração (minutos)</Label>
                    <Input
                        id="service-duration"
                        v-model.number="form.default_duration_minutes"
                        type="number"
                        min="1"
                        max="1440"
                    />
                    <InputError
                        :message="form.errors.default_duration_minutes"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="service-buffer-before"
                        >Intervalo antes (minutos)</Label
                    >
                    <Input
                        id="service-buffer-before"
                        v-model.number="form.buffer_before_minutes"
                        type="number"
                        min="0"
                        max="1440"
                    />
                    <InputError :message="form.errors.buffer_before_minutes" />
                </div>
                <div class="grid gap-2">
                    <Label for="service-buffer-after"
                        >Intervalo depois (minutos)</Label
                    >
                    <Input
                        id="service-buffer-after"
                        v-model.number="form.buffer_after_minutes"
                        type="number"
                        min="0"
                        max="1440"
                    />
                    <InputError :message="form.errors.buffer_after_minutes" />
                </div>
            </div>
        </div>

        <Separator />

        <div class="grid gap-4">
            <h3 class="text-sm font-medium">Preço e desconto</h3>
            <p class="text-sm text-muted-foreground">
                Modo simplificado: custo + margem desejada ajudam a calcular o
                preço, mas o preço praticado é sempre o valor informado abaixo.
                O desconto máximo é o limite que um desconto pode ultrapassar
                sem exigir aprovação numa venda.
            </p>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="service-cost">Custo estimado (opcional)</Label>
                    <Input
                        id="service-cost"
                        v-model.number="form.cost"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0,00"
                    />
                    <InputError :message="form.errors.cost" />
                </div>
                <div class="grid gap-2">
                    <Label for="service-margin">Margem desejada (%)</Label>
                    <Input
                        id="service-margin"
                        v-model.number="form.margin_percentage"
                        type="number"
                        min="0"
                        max="1000"
                        placeholder="0"
                    />
                    <InputError :message="form.errors.margin_percentage" />
                </div>
                <div class="grid gap-2">
                    <Label for="service-price"
                        >Preço praticado (opcional)</Label
                    >
                    <Input
                        id="service-price"
                        v-model.number="form.default_price"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0,00"
                    />
                    <InputError :message="form.errors.default_price" />
                </div>
            </div>
            <div class="grid gap-2 sm:max-w-xs">
                <Label for="service-max-discount"
                    >Desconto máximo sem aprovação (%)</Label
                >
                <Input
                    id="service-max-discount"
                    v-model.number="form.max_discount_percentage"
                    type="number"
                    min="0"
                    max="100"
                    placeholder="0"
                />
                <InputError :message="form.errors.max_discount_percentage" />
            </div>
        </div>

        <Separator />

        <div class="grid gap-4">
            <h3 class="text-sm font-medium">Especialidades associadas</h3>
            <p
                v-if="specialties.length === 0"
                class="text-sm text-muted-foreground"
            >
                Nenhuma especialidade ativa cadastrada ainda.
            </p>
            <div v-else class="grid gap-2 sm:grid-cols-2">
                <label
                    v-for="specialty in specialties"
                    :key="specialty.id"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        class="size-4 rounded border-input"
                        :checked="form.specialty_ids.includes(specialty.id)"
                        @change="
                            toggleSpecialty(
                                specialty.id,
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    {{ specialty.name }}
                </label>
            </div>
            <InputError :message="form.errors.specialty_ids" />
        </div>

        <Separator />

        <div class="grid gap-4">
            <h3 class="text-sm font-medium">Disponibilidade por unidade</h3>
            <p class="text-sm text-muted-foreground">
                Define em quais unidades este serviço poderá ser oferecido nas
                próximas etapas (agenda). Isso não cria horários nem
                disponibilidade de agenda agora.
            </p>
            <div class="grid gap-2">
                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="radio"
                        value="all_units"
                        v-model="form.unit_availability_scope"
                    />
                    Disponível em todas as unidades
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="radio"
                        value="selected_units"
                        v-model="form.unit_availability_scope"
                    />
                    Disponível somente nas unidades selecionadas
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="radio"
                        value="none"
                        v-model="form.unit_availability_scope"
                    />
                    Indisponível por enquanto
                </label>
            </div>
            <InputError :message="form.errors.unit_availability_scope" />

            <div v-if="showUnitSelection" class="grid gap-2 sm:grid-cols-2">
                <label
                    v-for="unit in units"
                    :key="unit.id"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        class="size-4 rounded border-input"
                        :checked="form.unit_ids.includes(unit.id)"
                        @change="
                            toggleUnit(
                                unit.id,
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    {{ unit.name }}
                    <span
                        v-if="!unit.is_active"
                        class="text-xs text-muted-foreground"
                        >(inativa)</span
                    >
                </label>
                <InputError :message="form.errors.unit_ids" />
            </div>
        </div>

        <Separator />

        <div class="grid gap-4">
            <h3 class="text-sm font-medium">Configuração pública futura</h3>
            <p class="text-sm text-muted-foreground">
                Estas opções apenas preparam o serviço para integração futura
                com o site da clínica — não publicam nada automaticamente.
            </p>
            <label class="flex items-center gap-2 text-sm">
                <input
                    v-model="form.is_public"
                    type="checkbox"
                    class="size-4 rounded border-input"
                />
                Exibir publicamente no futuro
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input
                    v-model="form.requires_manual_confirmation"
                    type="checkbox"
                    class="size-4 rounded border-input"
                />
                Exige confirmação manual
            </label>
        </div>

        <Separator />

        <div class="grid gap-2">
            <Label for="service-internal-notes"
                >Observações internas (opcional)</Label
            >
            <p class="text-xs text-muted-foreground">
                Visíveis apenas para a equipe da clínica — nunca aparecem no
                site público.
            </p>
            <Textarea
                id="service-internal-notes"
                v-model="form.internal_notes"
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
                {{ mode === 'create' ? 'Criar serviço' : 'Salvar alterações' }}
            </Button>
        </div>
    </form>
</template>

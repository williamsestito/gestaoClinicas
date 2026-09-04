<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/settings/products';

export type EditableProduct = {
    id: string;
    name: string;
    code: string;
    barcode: string | null;
    unit_of_measure: string;
    cost: number | null;
    margin_percentage: number | null;
    price: number | null;
    max_discount_percentage: number | null;
    internal_notes: string | null;
};

const props = withDefaults(
    defineProps<{
        mode: 'create' | 'edit';
        product?: EditableProduct;
    }>(),
    {
        product: undefined,
    },
);

const emit = defineEmits<{
    success: [];
    cancel: [];
}>();

const form = useForm({
    name: props.product?.name ?? '',
    code: props.product?.code ?? '',
    barcode: props.product?.barcode ?? '',
    unit_of_measure: props.product?.unit_of_measure ?? 'un',
    cost: props.product?.cost ?? (undefined as number | undefined),
    margin_percentage:
        props.product?.margin_percentage ?? (undefined as number | undefined),
    price: props.product?.price ?? (undefined as number | undefined),
    max_discount_percentage:
        props.product?.max_discount_percentage ??
        (undefined as number | undefined),
    internal_notes: props.product?.internal_notes ?? '',
});

function submit() {
    if (props.mode === 'create') {
        form.post(store().url, { onSuccess: () => emit('success') });

        return;
    }

    if (props.product) {
        form.put(update(props.product.id).url, {
            onSuccess: () => emit('success'),
        });
    }
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2 sm:col-span-2">
                <Label for="product-name">Nome</Label>
                <Input id="product-name" v-model="form.name" autofocus />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="product-code">Código</Label>
                <Input
                    id="product-code"
                    v-model="form.code"
                    placeholder="Ex.: PRD-01"
                />
                <InputError :message="form.errors.code" />
            </div>

            <div class="grid gap-2">
                <Label for="product-barcode">Código de barras (opcional)</Label>
                <Input id="product-barcode" v-model="form.barcode" />
                <InputError :message="form.errors.barcode" />
            </div>

            <div class="grid gap-2">
                <Label for="product-unit">Unidade de medida</Label>
                <Input
                    id="product-unit"
                    v-model="form.unit_of_measure"
                    placeholder="un, ml, g..."
                />
                <InputError :message="form.errors.unit_of_measure" />
            </div>
        </div>

        <Separator />

        <div class="grid gap-4">
            <h3 class="text-sm font-medium">Preço e desconto</h3>
            <p class="text-muted-foreground text-sm">
                Modo simplificado: custo + margem desejada ajudam a calcular o
                preço, mas o preço praticado é sempre o valor informado abaixo.
                O desconto máximo é o limite que um desconto pode ultrapassar
                sem exigir aprovação numa venda.
            </p>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="product-cost">Custo (opcional)</Label>
                    <Input
                        id="product-cost"
                        v-model.number="form.cost"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0,00"
                    />
                    <InputError :message="form.errors.cost" />
                </div>
                <div class="grid gap-2">
                    <Label for="product-margin">Margem desejada (%)</Label>
                    <Input
                        id="product-margin"
                        v-model.number="form.margin_percentage"
                        type="number"
                        min="0"
                        max="1000"
                        placeholder="0"
                    />
                    <InputError :message="form.errors.margin_percentage" />
                </div>
                <div class="grid gap-2">
                    <Label for="product-price"
                        >Preço praticado (opcional)</Label
                    >
                    <Input
                        id="product-price"
                        v-model.number="form.price"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0,00"
                    />
                    <InputError :message="form.errors.price" />
                </div>
            </div>
            <div class="grid gap-2 sm:max-w-xs">
                <Label for="product-max-discount"
                    >Desconto máximo sem aprovação (%)</Label
                >
                <Input
                    id="product-max-discount"
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

        <div class="grid gap-2">
            <Label for="product-internal-notes"
                >Observações internas (opcional)</Label
            >
            <Textarea
                id="product-internal-notes"
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
                {{ mode === 'create' ? 'Criar produto' : 'Salvar alterações' }}
            </Button>
        </div>
    </form>
</template>

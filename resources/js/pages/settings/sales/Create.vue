<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import PatientSearchSelect from '@/components/appointments/PatientSearchSelect.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatCurrencyBrl } from '@/lib/masks';
import { dashboard } from '@/routes';
import { index, store } from '@/routes/settings/sales';

type Option = { id: string; name: string };
type PriceableOption = Option & {
    default_price_cents?: number | null;
    price_cents?: number | null;
    max_discount_percentage: number | null;
};

const props = defineProps<{
    patient: { id: string; name: string } | null;
    unit: Option | null;
    legalEntity: Option | null;
    services: PriceableOption[];
    products: PriceableOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Produtos e vendas' },
            { title: 'Vendas', href: index() },
            { title: 'Nova venda' },
        ],
    },
});

type ItemRow = {
    item_type: 'service' | 'product' | 'service_package';
    service_id: string;
    product_id: string;
    session_count: number | undefined;
    quantity: number;
    discount_percentage: number;
    unit_price: number | undefined;
};

function newItem(): ItemRow {
    return {
        item_type: 'service',
        service_id: '',
        product_id: '',
        session_count: undefined,
        quantity: 1,
        discount_percentage: 0,
        unit_price: undefined,
    };
}

const items = ref<ItemRow[]>([newItem()]);

function addItem() {
    items.value.push(newItem());
}

function removeItem(index: number) {
    items.value.splice(index, 1);
}

function catalogPriceCents(item: ItemRow): number | null {
    if (item.item_type === 'product') {
        const product = props.products.find((p) => p.id === item.product_id);

        return product?.price_cents ?? null;
    }

    if (item.item_type === 'service') {
        const service = props.services.find((s) => s.id === item.service_id);

        return service?.default_price_cents ?? null;
    }

    return null;
}

function maxDiscountFor(item: ItemRow): number {
    const list = item.item_type === 'product' ? props.products : props.services;
    const id = item.item_type === 'product' ? item.product_id : item.service_id;
    const found = list.find((option) => option.id === id);

    return found?.max_discount_percentage ?? 0;
}

/**
 * Mesma regra do servidor (CreateSaleAction::resolveServicePricing/
 * resolveProductPricing): o preço de catálogo sempre prevalece quando
 * existe — o campo de preço manual só é usado quando não há preço de
 * tabela (ou sempre, no caso de pacote de sessões, que não tem catálogo).
 */
function unitPriceCentsFor(item: ItemRow): number {
    if (item.item_type !== 'service_package') {
        const catalogPrice = catalogPriceCents(item);

        if (catalogPrice !== null) {
            return catalogPrice;
        }
    }

    return item.unit_price !== undefined
        ? Math.round(item.unit_price * 100)
        : 0;
}

function hasCatalogPrice(item: ItemRow): boolean {
    return (
        item.item_type !== 'service_package' && catalogPriceCents(item) !== null
    );
}

function finalPriceCentsFor(item: ItemRow): number {
    const unitPriceCents = unitPriceCentsFor(item);

    return Math.round(
        item.quantity * unitPriceCents * (1 - item.discount_percentage / 100),
    );
}

function requiresApproval(item: ItemRow): boolean {
    return item.discount_percentage > maxDiscountFor(item);
}

const subtotalCents = computed(() =>
    items.value.reduce(
        (sum, item) => sum + item.quantity * unitPriceCentsFor(item),
        0,
    ),
);

const totalCents = computed(() =>
    items.value.reduce((sum, item) => sum + finalPriceCentsFor(item), 0),
);

const hasPendingApproval = computed(() => items.value.some(requiresApproval));

type ItemPayload = {
    item_type: string;
    service_id: string | null;
    product_id: string | null;
    session_count: number | null;
    quantity: number;
    discount_percentage: number;
    unit_price: number | undefined;
};

const form = useForm({
    patient_id: props.patient?.id ?? '',
    unit_id: props.unit?.id ?? '',
    legal_entity_id: props.legalEntity?.id ?? '',
    professional_id: '',
    appointment_id: '',
    items: [] as ItemPayload[],
});

function submit() {
    form.items = items.value.map((item): ItemPayload => ({
        item_type: item.item_type,
        service_id: item.item_type === 'product' ? null : item.service_id,
        product_id: item.item_type === 'product' ? item.product_id : null,
        session_count:
            item.item_type === 'service_package'
                ? (item.session_count ?? null)
                : null,
        quantity: item.quantity,
        discount_percentage: item.discount_percentage,
        unit_price: item.unit_price,
    }));

    form.post(store().url);
}
</script>

<template>
    <Head title="Nova venda" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Nova venda"
            description="Monte o carrinho com serviços, produtos e pacotes de sessões"
        />

        <p v-if="unit || legalEntity" class="text-sm text-muted-foreground">
            <span v-if="unit">Unidade: {{ unit.name }}</span>
            <span v-if="unit && legalEntity"> · </span>
            <span v-if="legalEntity"
                >Entidade legal: {{ legalEntity.name }}</span
            >
        </p>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Paciente</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="patient" class="rounded-md border p-2 text-sm">
                        {{ patient.name }}
                    </div>
                    <PatientSearchSelect
                        v-else
                        v-model="form.patient_id"
                        :error="form.errors.patient_id"
                    />
                    <InputError
                        v-if="patient"
                        :message="form.errors.patient_id"
                    />
                    <InputError :message="form.errors.unit_id" />
                    <InputError :message="form.errors.legal_entity_id" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Itens</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <thead
                                class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                            >
                                <tr>
                                    <th class="w-36 px-3 py-2">Tipo</th>
                                    <th class="px-3 py-2">Item</th>
                                    <th class="w-24 px-3 py-2">Qtd.</th>
                                    <th class="w-36 px-3 py-2">
                                        Preço unitário
                                    </th>
                                    <th class="w-24 px-3 py-2">Desconto %</th>
                                    <th class="w-32 px-3 py-2 text-right">
                                        Total
                                    </th>
                                    <th class="w-10 px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="(item, itemIndex) in items"
                                    :key="itemIndex"
                                    class="align-top"
                                >
                                    <td class="px-3 py-3">
                                        <Select v-model="item.item_type">
                                            <SelectTrigger class="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="service">
                                                    Serviço
                                                </SelectItem>
                                                <SelectItem value="product">
                                                    Produto
                                                </SelectItem>
                                                <SelectItem
                                                    value="service_package"
                                                >
                                                    Pacote de sessões
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </td>
                                    <td class="px-3 py-3">
                                        <Select
                                            v-if="item.item_type === 'product'"
                                            v-model="item.product_id"
                                        >
                                            <SelectTrigger class="w-full">
                                                <SelectValue
                                                    placeholder="Selecione o produto"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="product in products"
                                                    :key="product.id"
                                                    :value="product.id"
                                                >
                                                    {{ product.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Select
                                            v-else
                                            v-model="item.service_id"
                                        >
                                            <SelectTrigger class="w-full">
                                                <SelectValue
                                                    placeholder="Selecione o serviço"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="service in services"
                                                    :key="service.id"
                                                    :value="service.id"
                                                >
                                                    {{ service.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </td>
                                    <td class="px-3 py-3">
                                        <Input
                                            v-if="
                                                item.item_type ===
                                                'service_package'
                                            "
                                            v-model.number="item.session_count"
                                            type="number"
                                            min="1"
                                            placeholder="Sessões"
                                        />
                                        <Input
                                            v-else
                                            v-model.number="item.quantity"
                                            type="number"
                                            min="1"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <Input
                                            v-model.number="item.unit_price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            data-testid="item-unit-price"
                                            :disabled="hasCatalogPrice(item)"
                                            :placeholder="
                                                catalogPriceCents(item) !== null
                                                    ? formatCurrencyBrl(
                                                          catalogPriceCents(
                                                              item,
                                                          ) ?? 0,
                                                      )
                                                    : '0,00'
                                            "
                                        />
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            <template
                                                v-if="hasCatalogPrice(item)"
                                                >Preço de tabela</template
                                            >
                                            <template
                                                v-else-if="
                                                    item.item_type ===
                                                    'service_package'
                                                "
                                                >Total do pacote</template
                                            >
                                            <template v-else
                                                >Sem preço de tabela</template
                                            >
                                        </p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <Input
                                            v-model.number="
                                                item.discount_percentage
                                            "
                                            type="number"
                                            min="0"
                                            max="100"
                                            data-testid="item-discount-percentage"
                                        />
                                    </td>
                                    <td
                                        class="px-3 py-3 text-right font-medium"
                                    >
                                        {{
                                            formatCurrencyBrl(
                                                finalPriceCentsFor(item),
                                            )
                                        }}
                                        <p
                                            v-if="requiresApproval(item)"
                                            class="mt-1 text-xs text-amber-600"
                                            role="status"
                                        >
                                            Exige aprovação
                                        </p>
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            aria-label="Remover item"
                                            :disabled="items.length === 1"
                                            @click="removeItem(itemIndex)"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="border-t p-3">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addItem"
                            >
                                <Plus class="size-4" />
                                Adicionar item
                            </Button>
                        </div>
                    </div>

                    <InputError :message="form.errors.items" />
                </CardContent>
            </Card>

            <div
                class="flex flex-col gap-1 rounded-md border p-4 text-sm sm:items-end"
            >
                <p class="text-muted-foreground">
                    Subtotal: {{ formatCurrencyBrl(subtotalCents) }}
                </p>
                <p class="text-lg font-semibold">
                    Total: {{ formatCurrencyBrl(totalCents) }}
                </p>
                <p
                    v-if="hasPendingApproval"
                    class="text-amber-600"
                    role="status"
                >
                    Esta venda terá itens aguardando aprovação de desconto.
                </p>
            </div>

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    Criar venda
                </Button>
            </div>
        </form>
    </div>
</template>

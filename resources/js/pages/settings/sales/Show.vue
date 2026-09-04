<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrencyBrl, formatDateTimeBr } from '@/lib/masks';
import { dashboard } from '@/routes';
import {
    cancel,
    confirm as confirmRoute,
    index,
} from '@/routes/settings/sales';
import { approveDiscount } from '@/routes/settings/sales/items';

type SaleItem = {
    id: string;
    item_type: string;
    item_type_label: string;
    label: string;
    session_count: number | null;
    quantity: number;
    unit_price_cents: number;
    discount_percentage: number;
    final_price_cents: number;
    requires_approval: boolean;
    is_pending_approval: boolean;
    approver_name: string | null;
    approved_at: string | null;
    approval_justification: string | null;
};

type SaleDetail = {
    id: string;
    status: string;
    status_label: string;
    patient_name: string;
    unit_name: string;
    legal_entity_name: string;
    professional_name: string | null;
    subtotal_cents: number;
    discount_total_cents: number;
    total_cents: number;
    cancellation_reason: string | null;
    created_at: string;
    items: SaleItem[];
};

const props = defineProps<{
    sale: SaleDetail;
    canEdit: boolean;
    canConfirm: boolean;
    canCancel: boolean;
    canApproveDiscount: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Produtos e vendas' },
            { title: 'Vendas', href: index() },
            { title: 'Venda' },
        ],
    },
});

const confirmForm = useForm({});
const showConfirmDialog = ref(false);

function submitConfirm() {
    confirmForm.patch(confirmRoute(props.sale.id).url, {
        preserveScroll: true,
        onSuccess: () => (showConfirmDialog.value = false),
    });
}

const cancelForm = useForm({ reason: '' });
const showCancelForm = ref(false);

function submitCancel() {
    cancelForm.patch(cancel(props.sale.id).url, {
        preserveScroll: true,
        onSuccess: () => (showCancelForm.value = false),
    });
}

const approvalItem = ref<SaleItem | null>(null);
const approvalForm = useForm({ justification: '', password: '' });

function openApproval(item: SaleItem) {
    approvalItem.value = item;
    approvalForm.reset();
}

function submitApproval() {
    if (!approvalItem.value) {
        return;
    }

    approvalForm.patch(
        approveDiscount({ sale: props.sale.id, item: approvalItem.value.id })
            .url,
        {
            preserveScroll: true,
            onSuccess: () => (approvalItem.value = null),
        },
    );
}

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'confirmed') {
        return 'default';
    }

    if (status === 'cancelled') {
        return 'destructive';
    }

    if (status === 'pending_approval') {
        return 'outline';
    }

    return 'secondary';
}
</script>

<template>
    <Head :title="`Venda — ${sale.patient_name}`" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Venda"
            :description="`${sale.patient_name} — ${formatDateTimeBr(sale.created_at)}`"
        />

        <div class="flex items-center gap-2">
            <Badge :variant="statusVariant(sale.status)">
                {{ sale.status_label }}
            </Badge>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Dados</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-2 text-sm sm:grid-cols-2">
                <p>
                    <span class="text-muted-foreground">Unidade:</span>
                    {{ sale.unit_name }}
                </p>
                <p>
                    <span class="text-muted-foreground">Entidade legal:</span>
                    {{ sale.legal_entity_name }}
                </p>
                <p v-if="sale.professional_name">
                    <span class="text-muted-foreground">Profissional:</span>
                    {{ sale.professional_name }}
                </p>
                <p v-if="sale.cancellation_reason">
                    <span class="text-muted-foreground">
                        Motivo do cancelamento:
                    </span>
                    {{ sale.cancellation_reason }}
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Itens</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-3">
                <div
                    v-for="item in sale.items"
                    :key="item.id"
                    class="grid gap-1 rounded-md border p-3 text-sm"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-medium">
                            {{ item.label }}
                            <span class="text-muted-foreground">
                                ({{ item.item_type_label }})
                            </span>
                        </p>
                        <p class="font-medium">
                            {{ formatCurrencyBrl(item.final_price_cents) }}
                        </p>
                    </div>
                    <p class="text-muted-foreground">
                        Qtd. {{ item.quantity }} ·
                        {{ formatCurrencyBrl(item.unit_price_cents) }} un. ·
                        {{ item.discount_percentage }}% desconto
                        <template v-if="item.session_count">
                            · {{ item.session_count }} sessões
                        </template>
                    </p>

                    <div
                        v-if="item.is_pending_approval"
                        class="mt-1 flex items-center justify-between gap-2 rounded-md bg-amber-50 p-2 text-amber-700"
                    >
                        <span>Aguardando aprovação de desconto</span>
                        <Button
                            v-if="canApproveDiscount"
                            type="button"
                            size="sm"
                            @click="openApproval(item)"
                        >
                            Aprovar
                        </Button>
                    </div>
                    <p
                        v-else-if="item.requires_approval && item.approved_at"
                        class="text-xs text-muted-foreground"
                    >
                        Desconto aprovado por {{ item.approver_name }} em
                        {{ formatDateTimeBr(item.approved_at) }} —
                        {{ item.approval_justification }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="approvalItem"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        >
            <Card class="w-full max-w-md">
                <CardHeader>
                    <CardTitle class="text-base">
                        Aprovar desconto — {{ approvalItem.label }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <form class="grid gap-4" @submit.prevent="submitApproval">
                        <div class="grid gap-2">
                            <Label for="approval-justification">
                                Justificativa
                            </Label>
                            <Textarea
                                id="approval-justification"
                                v-model="approvalForm.justification"
                                rows="3"
                            />
                            <InputError
                                :message="approvalForm.errors.justification"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="approval-password">Sua senha</Label>
                            <Input
                                id="approval-password"
                                v-model="approvalForm.password"
                                type="password"
                                autocomplete="current-password"
                            />
                            <InputError
                                :message="approvalForm.errors.password"
                            />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                @click="approvalItem = null"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                :disabled="approvalForm.processing"
                            >
                                Confirmar aprovação
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Total</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-1 text-sm">
                <p>Subtotal: {{ formatCurrencyBrl(sale.subtotal_cents) }}</p>
                <p>
                    Descontos:
                    {{ formatCurrencyBrl(sale.discount_total_cents) }}
                </p>
                <p class="text-base font-semibold">
                    Total: {{ formatCurrencyBrl(sale.total_cents) }}
                </p>
            </CardContent>
        </Card>

        <div class="flex flex-wrap gap-2">
            <Button
                v-if="canConfirm"
                type="button"
                :disabled="confirmForm.processing"
                @click="showConfirmDialog = true"
            >
                Confirmar venda
            </Button>
            <Button
                v-if="canCancel && !showCancelForm"
                type="button"
                variant="destructive"
                @click="showCancelForm = true"
            >
                Cancelar venda
            </Button>
        </div>

        <Card v-if="showCancelForm">
            <CardContent class="grid gap-4 pt-6">
                <form class="grid gap-4" @submit.prevent="submitCancel">
                    <div class="grid gap-2">
                        <Label for="cancel-reason"
                            >Motivo do cancelamento</Label
                        >
                        <Textarea
                            id="cancel-reason"
                            v-model="cancelForm.reason"
                            rows="3"
                        />
                        <InputError :message="cancelForm.errors.reason" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            @click="showCancelForm = false"
                        >
                            Voltar
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="cancelForm.processing"
                        >
                            Confirmar cancelamento
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Dialog v-model:open="showConfirmDialog">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Confirmar venda?</DialogTitle>
                    <DialogDescription>
                        Após confirmada, a venda não poderá mais ser editada.
                        Itens de pacote de sessões serão liberados para uso
                        imediatamente.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Voltar</Button>
                    </DialogClose>
                    <Button
                        :disabled="confirmForm.processing"
                        @click="submitConfirm"
                    >
                        Confirmar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

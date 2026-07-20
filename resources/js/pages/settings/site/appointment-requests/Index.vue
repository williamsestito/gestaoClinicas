<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import { status } from '@/routes/settings/site/appointment-requests';
import type {
    AppointmentRequestStatus,
    AppointmentRequestSummary,
} from '@/types/site';

defineProps<{
    requests: AppointmentRequestSummary[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Presença digital' },
            { title: 'Site da clínica' },
            { title: 'Agendamentos' },
        ],
    },
});

const STATUS_OPTIONS: { value: AppointmentRequestStatus; label: string }[] = [
    { value: 'pending', label: 'Aguardando contato' },
    { value: 'contacted', label: 'Contato realizado' },
    { value: 'scheduled', label: 'Agendado' },
    { value: 'cancelled', label: 'Cancelado' },
];

const processingId = ref<string | null>(null);

function updateStatus(
    request: AppointmentRequestSummary,
    value: AppointmentRequestStatus,
) {
    processingId.value = request.id;
    router.patch(
        status(request.id).url,
        { status: value },
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}
</script>

<template>
    <Head title="Solicitações de agendamento" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Solicitações de agendamento"
            description="Leads enviados pelo formulário de agendamento da landing pública. A clínica confirma manualmente por telefone/WhatsApp/e-mail."
        />

        <EmptyState
            v-if="requests.length === 0"
            title="Nenhuma solicitação recebida ainda."
        />

        <div v-else class="grid gap-3">
            <Card v-for="request in requests" :key="request.id">
                <CardContent
                    class="flex flex-col gap-3 py-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="space-y-1">
                        <p class="font-medium">{{ request.name }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ request.phone }}
                            <template v-if="request.email">
                                · {{ request.email }}</template
                            >
                        </p>
                        <p
                            v-if="request.service_name"
                            class="text-sm text-muted-foreground"
                        >
                            Serviço: {{ request.service_name }}
                        </p>
                        <p
                            v-if="request.preferred_period"
                            class="text-sm text-muted-foreground"
                        >
                            Preferência: {{ request.preferred_period }}
                        </p>
                        <p
                            v-if="request.notes"
                            class="text-sm text-muted-foreground"
                        >
                            "{{ request.notes }}"
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Recebido em {{ formatDate(request.created_at) }}
                        </p>
                    </div>

                    <Select
                        :model-value="request.status"
                        :disabled="processingId === request.id"
                        @update:model-value="
                            (value) =>
                                updateStatus(
                                    request,
                                    value as AppointmentRequestStatus,
                                )
                        "
                    >
                        <SelectTrigger class="w-full sm:w-48">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in STATUS_OPTIONS"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

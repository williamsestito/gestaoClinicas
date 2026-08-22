<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MessageCircle } from '@lucide/vue';
import { reactive, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateBr, formatDateTimeBr } from '@/lib/masks';
import { buildWhatsAppUrl } from '@/lib/whatsapp';
import { dashboard } from '@/routes';
import { notes, status } from '@/routes/settings/my-appointment-requests';
import type { AppointmentRequestStatus } from '@/types/site';

type MyAppointmentRequest = {
    id: string;
    name: string;
    phone: string;
    email: string | null;
    service_name: string | null;
    preferred_period: string | null;
    preferred_date: string | null;
    notes: string | null;
    internal_notes: string | null;
    status: AppointmentRequestStatus;
    status_label: string;
    appointment_status: string | null;
    appointment_status_label: string | null;
    created_at: string | null;
};

type PaginatedRequests = {
    data: MyAppointmentRequest[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

const props = defineProps<{
    requests: PaginatedRequests | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Meus pré-agendamentos' },
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
const notesDrafts = reactive<Record<string, string>>(
    Object.fromEntries(
        (props.requests?.data ?? []).map((request) => [
            request.id,
            request.internal_notes ?? '',
        ]),
    ),
);
const savingNotesId = ref<string | null>(null);

function updateStatus(
    request: MyAppointmentRequest,
    value: AppointmentRequestStatus,
) {
    processingId.value = request.id;
    router.patch(
        status(request.id).url,
        { status: value },
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function saveNotes(request: MyAppointmentRequest) {
    savingNotesId.value = request.id;
    router.patch(
        notes(request.id).url,
        { internal_notes: notesDrafts[request.id] || null },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (savingNotesId.value = null),
        },
    );
}

function formatDate(value: string | null): string {
    return value ? formatDateTimeBr(value) : '—';
}

function formatPreferredDate(value: string | null): string | null {
    return value ? formatDateBr(value) : null;
}

function appointmentStatusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'cancelled' || status === 'no_show') {
        return 'destructive';
    }

    if (status === 'completed') {
        return 'secondary';
    }

    return 'default';
}

function whatsappLink(request: MyAppointmentRequest): string | null {
    const url = buildWhatsAppUrl(request.phone);

    if (!url) {
        return null;
    }

    const greeting = request.service_name
        ? `Olá, ${request.name}. Recebemos sua solicitação de agendamento para ${request.service_name}. Gostaríamos de confirmar a disponibilidade e os detalhes do atendimento.`
        : `Olá, ${request.name}. Recebemos sua solicitação de agendamento. Gostaríamos de confirmar a disponibilidade e os detalhes do atendimento.`;

    return `${url}?text=${encodeURIComponent(greeting)}`;
}
</script>

<template>
    <Head title="Meus pré-agendamentos" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Meus pré-agendamentos"
            description="Leads da landing pública que escolheram você — confirme por telefone/WhatsApp e atualize o status."
        />

        <EmptyState
            v-if="requests === null"
            title="Você não possui um cadastro profissional vinculado."
            description="Peça a um administrador da clínica para vincular seu usuário a um cadastro de profissional para ver seus pré-agendamentos."
        />

        <template v-else>
            <EmptyState
                v-if="requests.data.length === 0"
                title="Nenhum pré-agendamento encontrado."
                description="Solicitações feitas pela landing pública escolhendo você aparecerão aqui."
            />

            <div v-else class="grid gap-3">
                <Card v-for="request in requests.data" :key="request.id">
                    <CardContent class="flex flex-col gap-4 py-4">
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
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
                                    v-if="
                                        formatPreferredDate(
                                            request.preferred_date,
                                        ) || request.preferred_period
                                    "
                                    class="text-sm text-muted-foreground"
                                >
                                    Preferência:
                                    <template
                                        v-if="
                                            formatPreferredDate(
                                                request.preferred_date,
                                            )
                                        "
                                        >{{
                                            formatPreferredDate(
                                                request.preferred_date,
                                            )
                                        }}</template
                                    >
                                    <template v-if="request.preferred_period">
                                        {{
                                            formatPreferredDate(
                                                request.preferred_date,
                                            )
                                                ? ' — '
                                                : ''
                                        }}{{
                                            request.preferred_period
                                        }}</template
                                    >
                                </p>
                                <p
                                    v-if="request.notes"
                                    class="text-sm text-muted-foreground"
                                >
                                    "{{ request.notes }}"
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Recebido em
                                    {{ formatDate(request.created_at) }}
                                </p>
                                <p
                                    v-if="request.appointment_status_label"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    Agendamento real:
                                    <Badge
                                        :variant="
                                            appointmentStatusVariant(
                                                request.appointment_status ??
                                                    '',
                                            )
                                        "
                                    >
                                        {{ request.appointment_status_label }}
                                    </Badge>
                                </p>
                            </div>

                            <div
                                class="flex flex-col items-stretch gap-2 sm:items-end"
                            >
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

                                <a
                                    v-if="whatsappLink(request)"
                                    :href="whatsappLink(request) ?? undefined"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="w-full sm:w-48"
                                    >
                                        <MessageCircle class="size-4" />
                                        WhatsApp
                                    </Button>
                                </a>
                            </div>
                        </div>

                        <div class="grid gap-2 border-t pt-3">
                            <Label :for="`internal-notes-${request.id}`"
                                >Observação interna (não visível ao
                                paciente)</Label
                            >
                            <Textarea
                                :id="`internal-notes-${request.id}`"
                                v-model="notesDrafts[request.id]"
                                rows="2"
                            />
                            <Button
                                variant="secondary"
                                size="sm"
                                class="w-fit"
                                :disabled="savingNotesId === request.id"
                                @click="saveNotes(request)"
                            >
                                <Spinner v-if="savingNotesId === request.id" />
                                Salvar observação
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <nav
                v-if="requests.links.length > 3"
                aria-label="Paginação dos meus pré-agendamentos"
                class="flex flex-wrap gap-1"
            >
                <template
                    v-for="(link, linkIndex) in requests.links"
                    :key="linkIndex"
                >
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        :aria-current="link.active ? 'page' : undefined"
                        :class="[
                            'rounded-md px-3 py-1 text-sm',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted',
                        ]"
                        preserve-scroll
                    >
                        <span v-html="link.label" />
                    </Link>
                    <span
                        v-else
                        class="pointer-events-none rounded-md px-3 py-1 text-sm text-muted-foreground opacity-50"
                        aria-disabled="true"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </template>
    </div>
</template>

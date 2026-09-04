<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MessageCircle } from '@lucide/vue';
import { reactive, ref } from 'vue';
import InstantScheduleModal from '@/components/appointment-requests/InstantScheduleModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
import { create as createAppointment } from '@/routes/settings/appointments';
import {
    index,
    notes,
    professional as professionalRoute,
    status,
} from '@/routes/settings/site/appointment-requests';
import type {
    AppointmentRequestStatus,
    AppointmentRequestSummary,
} from '@/types/site';

type PaginatedRequests = {
    data: AppointmentRequestSummary[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

const props = defineProps<{
    requests: PaginatedRequests;
    professionals: { id: string; display_name: string }[];
    filters: {
        status?: string;
        search?: string;
        from?: string;
        to?: string;
        professional_id?: string;
    };
    can_create_appointments: boolean;
}>();

function canConvert(request: AppointmentRequestSummary): boolean {
    return (
        props.can_create_appointments &&
        (request.status === 'pending' || request.status === 'contacted') &&
        !request.appointment_status_label
    );
}

function convertUrl(request: AppointmentRequestSummary): string {
    // O controller resolve nome/telefone/observações a partir do próprio
    // AppointmentRequest no servidor — só o id precisa ir na query string
    // (evita vazar dado do lead em logs de acesso).
    const params = new URLSearchParams({ appointment_request_id: request.id });

    return `${createAppointment().url}?${params.toString()}`;
}

// Mesma regra de canInstantSchedule() de "Meus pré-agendamentos" — admin e
// atendimento também confirmam direto por aqui quando o lead já carrega
// unidade/serviço/horário exatos, para qualquer profissional da
// organização (ver InstantScheduleModal.vue).
function canInstantSchedule(request: AppointmentRequestSummary): boolean {
    return (
        canConvert(request) &&
        !!request.unit_id &&
        !!request.preferred_service_id &&
        !!request.preferred_starts_at
    );
}

const instantScheduleRequest = ref<AppointmentRequestSummary | null>(null);

function openInstantSchedule(request: AppointmentRequestSummary) {
    instantScheduleRequest.value = request;
}

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

const STATUS_FILTER_OPTIONS: {
    value: AppointmentRequestStatus;
    label: string;
}[] = [
    { value: 'pending', label: 'Aguardando contato' },
    { value: 'contacted', label: 'Contato realizado' },
    { value: 'scheduled', label: 'Agendado' },
    { value: 'cancelled', label: 'Cancelado' },
];

// "Agendado" nunca é uma opção editável aqui — só existe de verdade quando
// o pré-agendamento é convertido em Appointment real pelo botão "Agendar"
// (ver App\Actions\Organization\CreateAppointmentAction). Continua
// disponível como filtro acima, para localizar quem já foi convertido.
const EDITABLE_STATUS_OPTIONS = STATUS_FILTER_OPTIONS.filter(
    (option) => option.value !== 'scheduled',
);

const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');
const professionalFilter = ref(props.filters.professional_id ?? '');
const fromDate = ref(props.filters.from ?? '');
const toDate = ref(props.filters.to ?? '');

const processingId = ref<string | null>(null);
const notesDrafts = reactive<Record<string, string>>(
    Object.fromEntries(
        props.requests.data.map((request) => [
            request.id,
            request.internal_notes ?? '',
        ]),
    ),
);
const savingNotesId = ref<string | null>(null);
const savingProfessionalId = ref<string | null>(null);

function applyFilters() {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            professional_id: professionalFilter.value || undefined,
            from: fromDate.value || undefined,
            to: toDate.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

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

function reassignProfessional(
    request: AppointmentRequestSummary,
    professionalId: string | null,
) {
    savingProfessionalId.value = request.id;
    router.patch(
        professionalRoute(request.id).url,
        { professional_id: professionalId },
        {
            preserveScroll: true,
            onFinish: () => (savingProfessionalId.value = null),
        },
    );
}

function saveNotes(request: AppointmentRequestSummary) {
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
    if (!value) {
        return '—';
    }

    return formatDateTimeBr(value);
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

function whatsappLink(request: AppointmentRequestSummary): string | null {
    const url = buildWhatsAppUrl(request.phone);

    if (!url) {
        return null;
    }

    const greeting = request.service_name
        ? `Olá, ${request.name}. Recebemos sua solicitação de agendamento para ${request.service_name}. Gostaríamos de confirmar a disponibilidade e os detalhes do atendimento.`
        : `Olá, ${request.name}. Recebemos sua solicitação de agendamento. Gostaríamos de confirmar a disponibilidade e os detalhes do atendimento.`;

    return `${url}?text=${encodeURIComponent(greeting)}`;
}

function utmEntries(request: AppointmentRequestSummary): [string, string][] {
    return Object.entries(request.utm_data ?? {});
}
</script>

<template>
    <Head title="Solicitações de agendamento" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Solicitações de agendamento"
            description="Leads enviados pelo formulário de agendamento da landing pública. A clínica confirma manualmente por telefone/WhatsApp/e-mail."
        />

        <form
            class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end"
            @submit.prevent="applyFilters"
        >
            <div class="grid gap-2">
                <Label for="request-search">Nome ou telefone</Label>
                <Input
                    id="request-search"
                    v-model="search"
                    placeholder="Buscar"
                />
            </div>

            <div class="grid gap-2">
                <Label for="request-status">Status</Label>
                <select
                    id="request-status"
                    v-model="statusFilter"
                    class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="">Todos</option>
                    <option
                        v-for="option in STATUS_FILTER_OPTIONS"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>

            <div class="grid gap-2">
                <Label for="request-professional">Profissional</Label>
                <select
                    id="request-professional"
                    v-model="professionalFilter"
                    class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="">Todos</option>
                    <option
                        v-for="professional in professionals"
                        :key="professional.id"
                        :value="professional.id"
                    >
                        {{ professional.display_name }}
                    </option>
                </select>
            </div>

            <div class="grid gap-2">
                <Label for="request-from">De</Label>
                <Input id="request-from" v-model="fromDate" type="date" />
            </div>

            <div class="grid gap-2">
                <Label for="request-to">Até</Label>
                <Input id="request-to" v-model="toDate" type="date" />
            </div>

            <Button type="submit">Filtrar</Button>
        </form>

        <EmptyState
            v-if="requests.data.length === 0"
            title="Nenhuma solicitação encontrada."
            description="Ajuste os filtros ou aguarde novas solicitações da landing pública."
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
                                v-if="!request.professional_removed"
                                class="text-sm text-muted-foreground"
                            >
                                Profissional:
                                {{
                                    request.professional_name ?? 'Não definido'
                                }}
                            </p>
                            <div v-else class="grid gap-1.5">
                                <p class="text-sm text-destructive">
                                    Profissional "{{
                                        request.professional_name
                                    }}" não faz mais parte da equipe.
                                </p>
                                <Select
                                    :disabled="
                                        savingProfessionalId === request.id
                                    "
                                    @update:model-value="
                                        (value) =>
                                            reassignProfessional(
                                                request,
                                                value === 'none'
                                                    ? null
                                                    : (value as string),
                                            )
                                    "
                                >
                                    <SelectTrigger class="w-full sm:w-64">
                                        <SelectValue
                                            placeholder="Reatribuir profissional"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none"
                                            >Sem profissional
                                            definido</SelectItem
                                        >
                                        <SelectItem
                                            v-for="professional in professionals"
                                            :key="professional.id"
                                            :value="professional.id"
                                        >
                                            {{ professional.display_name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
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
                                    }}{{ request.preferred_period }}</template
                                >
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
                            <p
                                v-if="request.appointment_status_label"
                                class="flex items-center gap-2 text-sm"
                            >
                                Agendamento real:
                                <Badge
                                    :variant="
                                        appointmentStatusVariant(
                                            request.appointment_status ?? '',
                                        )
                                    "
                                >
                                    {{ request.appointment_status_label }}
                                </Badge>
                            </p>
                            <p
                                v-if="utmEntries(request).length > 0"
                                class="text-xs text-muted-foreground"
                            >
                                Origem:
                                <span
                                    v-for="([key, value], index) in utmEntries(
                                        request,
                                    )"
                                    :key="key"
                                >
                                    <template v-if="index > 0">, </template
                                    >{{ key }}={{ value }}
                                </span>
                            </p>
                        </div>

                        <div
                            class="flex flex-col items-stretch gap-2 sm:items-end"
                        >
                            <Select
                                :model-value="request.status"
                                :disabled="
                                    processingId === request.id ||
                                    !!request.appointment_status_label
                                "
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
                                        v-for="option in EDITABLE_STATUS_OPTIONS"
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

                            <Button
                                v-if="canInstantSchedule(request)"
                                size="sm"
                                class="w-full sm:w-48"
                                @click="openInstantSchedule(request)"
                            >
                                Confirmar agendamento
                            </Button>

                            <Link
                                v-else-if="canConvert(request)"
                                :href="convertUrl(request)"
                            >
                                <Button size="sm" class="w-full sm:w-48">
                                    Converter em agendamento
                                </Button>
                            </Link>
                        </div>
                    </div>

                    <div class="grid gap-2 border-t pt-3">
                        <Label :for="`internal-notes-${request.id}`"
                            >Observação interna (não visível ao paciente)</Label
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
            aria-label="Paginação das solicitações"
            class="flex flex-wrap gap-1"
        >
            <template v-for="(link, index) in requests.links" :key="index">
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

        <InstantScheduleModal
            :request="instantScheduleRequest"
            @close="instantScheduleRequest = null"
        />
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/audit';

type AuditLogEntry = {
    id: string;
    actor: string;
    action: string;
    action_label: string;
    entity: string;
    unit: string | null;
    ip_address: string | null;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    created_at: string | null;
};

type PaginatedLogs = {
    data: AuditLogEntry[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

const props = defineProps<{
    logs: PaginatedLogs;
    filters: {
        action?: string;
        entity?: string;
        search?: string;
        from?: string;
        to?: string;
    };
    actionOptions: { value: string; label: string }[];
}>();

const search = ref(props.filters.search ?? '');
const actionFilter = ref(props.filters.action ?? '');
const fromDate = ref(props.filters.from ?? '');
const toDate = ref(props.filters.to ?? '');

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Sistema' },
            { title: 'Auditoria' },
        ],
    },
});

function applyFilters() {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            action: actionFilter.value || undefined,
            from: fromDate.value || undefined,
            to: toDate.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function formatValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return typeof value === 'object' ? JSON.stringify(value) : String(value);
}
</script>

<template>
    <Head title="Auditoria" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Auditoria"
            description="Histórico de alterações realizadas na clínica."
        />

        <form
            class="flex flex-col gap-3 sm:flex-row sm:items-end"
            @submit.prevent="applyFilters"
        >
            <div class="grid gap-2">
                <label
                    for="audit-search"
                    class="text-sm font-medium text-muted-foreground"
                >
                    Usuário
                </label>
                <Input
                    id="audit-search"
                    v-model="search"
                    placeholder="Nome do usuário"
                />
            </div>

            <div class="grid gap-2">
                <label
                    for="audit-action"
                    class="text-sm font-medium text-muted-foreground"
                >
                    Ação
                </label>
                <select
                    id="audit-action"
                    v-model="actionFilter"
                    class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="">Todas</option>
                    <option
                        v-for="option in actionOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>

            <div class="grid gap-2">
                <label
                    for="audit-from"
                    class="text-sm font-medium text-muted-foreground"
                >
                    De
                </label>
                <Input id="audit-from" v-model="fromDate" type="date" />
            </div>

            <div class="grid gap-2">
                <label
                    for="audit-to"
                    class="text-sm font-medium text-muted-foreground"
                >
                    Até
                </label>
                <Input id="audit-to" v-model="toDate" type="date" />
            </div>

            <Button type="submit">Filtrar</Button>
        </form>

        <EmptyState
            v-if="logs.data.length === 0"
            title="Nenhum registro de auditoria encontrado."
            description="Ajuste os filtros ou aguarde novas atividades na clínica."
        />

        <div v-else class="overflow-x-auto rounded-md border">
            <table class="w-full text-sm">
                <caption class="sr-only">
                    Histórico de auditoria,
                    {{
                        logs.total
                    }}
                    registros no total
                </caption>
                <thead
                    class="border-b bg-muted/50 text-left text-muted-foreground"
                >
                    <tr>
                        <th class="px-4 py-2 font-medium">Usuário</th>
                        <th class="px-4 py-2 font-medium">Ação</th>
                        <th class="px-4 py-2 font-medium">Entidade</th>
                        <th class="px-4 py-2 font-medium">Unidade</th>
                        <th class="px-4 py-2 font-medium">Data</th>
                        <th class="px-4 py-2 font-medium">Resumo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="log in logs.data"
                        :key="log.id"
                        class="border-b align-top last:border-0"
                    >
                        <td class="px-4 py-3 font-medium">{{ log.actor }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ log.action_label }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ log.entity }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ log.unit ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{
                                log.created_at
                                    ? new Date(log.created_at).toLocaleString(
                                          'pt-BR',
                                      )
                                    : '—'
                            }}
                        </td>
                        <td class="px-4 py-3 text-xs text-muted-foreground">
                            <Card v-if="log.after" class="mb-1">
                                <CardContent class="space-y-0.5 p-2">
                                    <p
                                        v-for="(value, key) in log.after"
                                        :key="key"
                                    >
                                        <strong>{{ key }}:</strong>
                                        {{ formatValue(value) }}
                                    </p>
                                </CardContent>
                            </Card>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="logs.links.length > 3"
            aria-label="Paginação da auditoria"
            class="flex flex-wrap gap-1"
        >
            <template v-for="(link, index) in logs.links" :key="index">
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
    </div>
</template>

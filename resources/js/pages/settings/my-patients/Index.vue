<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import { patientHistory } from '@/routes/settings/medical-records';
import { index } from '@/routes/settings/my-patients';
import { edit } from '@/routes/settings/patients';
import { create as createSale } from '@/routes/settings/sales';

type PatientRow = {
    id: string;
    name: string;
    preferred_name: string | null;
    document: string | null;
    birth_date: string;
    phone: string | null;
    status: 'active' | 'inactive';
    deleted_at: string | null;
};

type PaginatedPatients = {
    data: PatientRow[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

const props = defineProps<{
    patients: PaginatedPatients | null;
    filters: { search?: string; status?: string };
}>();

const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Meus pacientes' },
        ],
    },
});

function applyFilters() {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            status: statusFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Meus pacientes" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Meus pacientes"
            description="Pacientes sob seus cuidados como profissional principal."
        />

        <EmptyState
            v-if="patients === null"
            title="Você não possui um cadastro profissional vinculado."
            description="Peça a um administrador da clínica para vincular seu usuário a um cadastro de profissional para ver seus pacientes."
        />

        <template v-else>
            <form
                class="flex flex-col gap-3 sm:flex-row sm:items-end"
                @submit.prevent="applyFilters"
            >
                <div class="grid gap-2">
                    <label
                        for="my-patient-search"
                        class="text-sm font-medium text-muted-foreground"
                    >
                        Buscar
                    </label>
                    <Input
                        id="my-patient-search"
                        v-model="search"
                        placeholder="Nome, documento, telefone ou e-mail"
                        class="sm:w-80"
                    />
                </div>
                <div class="grid gap-2">
                    <label
                        for="my-patient-status"
                        class="text-sm font-medium text-muted-foreground"
                    >
                        Status
                    </label>
                    <select
                        id="my-patient-status"
                        v-model="statusFilter"
                        class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">Todos</option>
                        <option value="active">Ativos</option>
                        <option value="inactive">Inativos</option>
                    </select>
                </div>
                <Button type="submit" variant="outline">Filtrar</Button>
            </form>

            <EmptyState
                v-if="patients.data.length === 0"
                title="Nenhum paciente encontrado"
                description="Ajuste os filtros ou aguarde novos pacientes serem vinculados a você."
            />

            <div v-else class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead
                        class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3">Nome</th>
                            <th class="px-4 py-3">Documento</th>
                            <th class="px-4 py-3">Nascimento</th>
                            <th class="px-4 py-3">Telefone</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="patient in patients.data" :key="patient.id">
                            <td class="px-4 py-3">
                                {{ patient.preferred_name || patient.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ patient.document ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{
                                    new Date(
                                        `${patient.birth_date}T00:00:00`,
                                    ).toLocaleDateString('pt-BR')
                                }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ patient.phone ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :status="patient.status"
                                    :deleted-at="patient.deleted_at"
                                />
                            </td>
                            <td
                                class="flex justify-end gap-2 px-4 py-3 text-right"
                            >
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="edit(patient.id).url">
                                        Ver
                                    </Link>
                                </Button>
                                <Button variant="outline" size="sm" as-child>
                                    <Link
                                        :href="patientHistory(patient.id).url"
                                    >
                                        Prontuário
                                    </Link>
                                </Button>
                                <Button variant="outline" size="sm" as-child>
                                    <Link
                                        :href="
                                            createSale({
                                                query: {
                                                    patient_id: patient.id,
                                                },
                                            }).url
                                        "
                                    >
                                        Vender
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="patients.links.length > 3"
                aria-label="Paginação dos meus pacientes"
                class="flex flex-wrap gap-1"
            >
                <template
                    v-for="(link, linkIndex) in patients.links"
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

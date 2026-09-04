<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import type { EditableResource } from '@/components/resources/ResourceForm.vue';
import ResourceForm from '@/components/resources/ResourceForm.vue';
import ResourceRowActions from '@/components/resources/ResourceRowActions.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDateTimeBr } from '@/lib/masks';
import { dashboard } from '@/routes';
import {
    activate,
    deactivate,
    destroy,
    restore as restoreRoute,
} from '@/routes/settings/resources';

export type ResourceRow = EditableResource & {
    unit_name: string;
    status: 'active' | 'inactive';
    appointments_count: number;
    deleted_at: string | null;
    updated_at: string;
};

const props = defineProps<{
    resources: ResourceRow[];
    units: { id: string; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Agenda' },
            { title: 'Recursos' },
        ],
    },
});

const search = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive' | 'deleted'>('all');

const indicators = computed(() => ({
    total: props.resources.length,
    active: props.resources.filter(
        (resource) => !resource.deleted_at && resource.status === 'active',
    ).length,
    inactive: props.resources.filter(
        (resource) => !resource.deleted_at && resource.status === 'inactive',
    ).length,
    deleted: props.resources.filter((resource) => resource.deleted_at).length,
}));

const filteredResources = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.resources.filter((resource) => {
        const matchesSearch =
            term === '' ||
            resource.name.toLowerCase().includes(term) ||
            resource.unit_name.toLowerCase().includes(term);

        const matchesStatus =
            statusFilter.value === 'all'
                ? !resource.deleted_at
                : statusFilter.value === 'deleted'
                  ? Boolean(resource.deleted_at)
                  : !resource.deleted_at &&
                    resource.status === statusFilter.value;

        return matchesSearch && matchesStatus;
    });
});

const hasAny = computed(() => props.resources.length > 0);
const hasFilteredResults = computed(() => filteredResources.value.length > 0);

const sheetOpen = ref(false);
const sheetMode = ref<'create' | 'edit'>('create');
const editingResource = ref<ResourceRow | null>(null);
const processingId = ref<string | null>(null);
const pendingDeletion = ref<ResourceRow | null>(null);

function openCreateSheet() {
    sheetMode.value = 'create';
    editingResource.value = null;
    sheetOpen.value = true;
}

function openEditSheet(resource: ResourceRow) {
    sheetMode.value = 'edit';
    editingResource.value = resource;
    sheetOpen.value = true;
}

function onFormSuccess() {
    sheetOpen.value = false;
}

function toggleActivate(resource: ResourceRow) {
    processingId.value = resource.id;
    const routeFn = resource.status === 'active' ? deactivate : activate;
    router.patch(
        routeFn(resource.id).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function confirmDelete() {
    if (!pendingDeletion.value) {
        return;
    }

    const id = pendingDeletion.value.id;
    processingId.value = id;
    router.delete(destroy(id).url, {
        preserveScroll: true,
        onFinish: () => {
            processingId.value = null;
            pendingDeletion.value = null;
        },
    });
}

function restore(resource: ResourceRow) {
    processingId.value = resource.id;
    router.post(
        restoreRoute(resource.id).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function formatDate(value: string): string {
    return formatDateTimeBr(value);
}
</script>

<template>
    <Head title="Recursos" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Recursos"
            description="Salas e equipamentos compartilhados que podem ser vinculados a um agendamento."
        >
            <template #actions>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Novo recurso
                </Button>
            </template>
        </PageHeader>

        <div v-if="hasAny" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Card>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">Total</p>
                    <p class="text-2xl font-semibold">{{ indicators.total }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">Ativos</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.active }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">Inativos</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.inactive }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">Excluídos</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.deleted }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div
            v-if="hasAny"
            class="flex flex-col gap-3 sm:flex-row sm:items-center"
        >
            <div class="relative sm:max-w-xs sm:flex-1">
                <Search
                    class="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Buscar por nome ou unidade"
                    aria-label="Buscar recursos por nome ou unidade"
                    class="pl-8"
                />
            </div>

            <select
                v-model="statusFilter"
                aria-label="Filtrar recursos por status"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Todos</option>
                <option value="active">Ativos</option>
                <option value="inactive">Inativos</option>
                <option value="deleted">Excluídos</option>
            </select>
        </div>

        <EmptyState
            v-if="!hasAny"
            title="Nenhum recurso cadastrado ainda."
            description="Cadastre salas ou equipamentos que precisam ser reservados junto com o profissional."
        >
            <template #action>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Cadastrar primeiro recurso
                </Button>
            </template>
        </EmptyState>

        <EmptyState
            v-else-if="!hasFilteredResults"
            title="Nenhum recurso corresponde aos filtros informados."
        />

        <template v-else>
            <div class="hidden overflow-x-auto rounded-md border md:block">
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/50 text-left text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-2 font-medium">Recurso</th>
                            <th class="px-4 py-2 font-medium">Unidade</th>
                            <th class="px-4 py-2 font-medium">Tipo</th>
                            <th class="px-4 py-2 font-medium">Agendamentos</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 font-medium">Atualizado em</th>
                            <th class="px-4 py-2 font-medium">
                                <span class="sr-only">Ações</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="resource in filteredResources"
                            :key="resource.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ resource.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ resource.unit_name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ resource.type || '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ resource.appointments_count }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :status="resource.status"
                                    :deleted-at="resource.deleted_at"
                                />
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ formatDate(resource.updated_at) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <ResourceRowActions
                                    :resource="resource"
                                    :disabled="processingId === resource.id"
                                    @edit="openEditSheet(resource)"
                                    @activate="toggleActivate(resource)"
                                    @deactivate="toggleActivate(resource)"
                                    @delete="pendingDeletion = resource"
                                    @restore="restore(resource)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3 md:hidden">
                <Card v-for="resource in filteredResources" :key="resource.id">
                    <CardContent class="flex flex-col gap-2 py-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-medium">{{ resource.name }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ resource.unit_name }}
                                </p>
                            </div>
                            <ResourceRowActions
                                :resource="resource"
                                :disabled="processingId === resource.id"
                                @edit="openEditSheet(resource)"
                                @activate="toggleActivate(resource)"
                                @deactivate="toggleActivate(resource)"
                                @delete="pendingDeletion = resource"
                                @restore="restore(resource)"
                            />
                        </div>

                        <StatusBadge
                            :status="resource.status"
                            :deleted-at="resource.deleted_at"
                        />

                        <p class="text-sm text-muted-foreground">
                            {{ resource.appointments_count }} agendamento(s)
                        </p>
                    </CardContent>
                </Card>
            </div>
        </template>

        <Sheet v-model:open="sheetOpen">
            <SheetContent
                side="right"
                class="w-full gap-0 overflow-y-auto sm:max-w-xl"
            >
                <SheetHeader>
                    <SheetTitle>
                        {{
                            sheetMode === 'create'
                                ? 'Novo recurso'
                                : 'Editar recurso'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        {{
                            sheetMode === 'create'
                                ? 'Cadastre uma nova sala ou equipamento.'
                                : `Atualize os dados de ${editingResource?.name ?? 'recurso'}.`
                        }}
                    </SheetDescription>
                </SheetHeader>

                <div class="px-4 pb-6">
                    <ResourceForm
                        v-if="sheetOpen"
                        :key="editingResource?.id ?? 'create'"
                        :mode="sheetMode"
                        :resource="editingResource ?? undefined"
                        :units="units"
                        @success="onFormSuccess"
                        @cancel="sheetOpen = false"
                    />
                </div>
            </SheetContent>
        </Sheet>

        <Dialog
            :open="pendingDeletion !== null"
            @update:open="(open) => !open && (pendingDeletion = null)"
        >
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Excluir recurso?</DialogTitle>
                    <DialogDescription>
                        Este registro será removido da operação, mas seu
                        histórico será preservado. Você poderá restaurá-lo
                        depois, se necessário.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancelar</Button>
                    </DialogClose>
                    <Button variant="destructive" @click="confirmDelete"
                        >Excluir</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

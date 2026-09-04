<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import type { EditableLegalEntity } from '@/components/legal-entities/LegalEntityForm.vue';
import LegalEntityForm from '@/components/legal-entities/LegalEntityForm.vue';
import LegalEntityRowActions from '@/components/legal-entities/LegalEntityRowActions.vue';
import PageHeader from '@/components/PageHeader.vue';
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
import { dashboard } from '@/routes';
import {
    destroy,
    primary as primaryRoute,
    restore as restoreRoute,
    status as statusRoute,
} from '@/routes/settings/legal-entities';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Configurações da clínica' },
            { title: 'Entidades legais' },
        ],
    },
});

const props = defineProps<{
    legalEntities: EditableLegalEntity[];
    legalEntityTypes: { value: string; label: string }[];
    states: string[];
}>();

const search = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive' | 'deleted'>('all');
const onlyPrimary = ref(false);

const indicators = computed(() => ({
    total: props.legalEntities.length,
    active: props.legalEntities.filter(
        (entity) => !entity.deleted_at && entity.status === 'active',
    ).length,
    inactive: props.legalEntities.filter(
        (entity) => !entity.deleted_at && entity.status === 'inactive',
    ).length,
    deleted: props.legalEntities.filter((entity) => entity.deleted_at).length,
}));

const filteredEntities = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.legalEntities.filter((entity) => {
        const matchesSearch =
            term === '' ||
            entity.legal_name.toLowerCase().includes(term) ||
            entity.document.toLowerCase().includes(term);

        const matchesStatus =
            statusFilter.value === 'all'
                ? true
                : statusFilter.value === 'deleted'
                  ? Boolean(entity.deleted_at)
                  : !entity.deleted_at && entity.status === statusFilter.value;

        const matchesPrimary = !onlyPrimary.value || entity.is_primary;

        return matchesSearch && matchesStatus && matchesPrimary;
    });
});

const hasAnyEntities = computed(() => props.legalEntities.length > 0);
const hasFilteredResults = computed(() => filteredEntities.value.length > 0);
const hasActiveFilters = computed(
    () =>
        search.value.trim() !== '' ||
        statusFilter.value !== 'all' ||
        onlyPrimary.value,
);

const sheetOpen = ref(false);
const sheetMode = ref<'create' | 'edit'>('create');
const editingEntity = ref<EditableLegalEntity | null>(null);
const processingId = ref<string | null>(null);
const entityPendingDeletion = ref<EditableLegalEntity | null>(null);

function openCreateSheet() {
    sheetMode.value = 'create';
    editingEntity.value = null;
    sheetOpen.value = true;
}

function openEditSheet(entity: EditableLegalEntity) {
    sheetMode.value = 'edit';
    editingEntity.value = entity;
    sheetOpen.value = true;
}

function onFormSuccess() {
    sheetOpen.value = false;
}

function toggleStatus(entity: EditableLegalEntity) {
    processingId.value = entity.id;
    router.patch(
        statusRoute(entity.id).url,
        { active: entity.status !== 'active' },
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function makePrimary(entity: EditableLegalEntity) {
    processingId.value = entity.id;
    router.put(
        primaryRoute(entity.id).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function confirmDelete() {
    if (!entityPendingDeletion.value) {
        return;
    }

    const id = entityPendingDeletion.value.id;
    processingId.value = id;
    router.delete(destroy(id).url, {
        preserveScroll: true,
        onFinish: () => {
            processingId.value = null;
            entityPendingDeletion.value = null;
        },
    });
}

function restore(entity: EditableLegalEntity) {
    processingId.value = entity.id;
    router.post(
        restoreRoute(entity.id).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}
</script>

<template>
    <Head title="Entidades legais" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Entidades legais"
            description="Gerencie as entidades legais (CPF/CNPJ) da sua clínica."
        >
            <template #actions>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Nova entidade legal
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="hasAnyEntities"
            class="grid grid-cols-2 gap-3 sm:grid-cols-4"
        >
            <Card>
                <CardContent class="py-4">
                    <p class="text-muted-foreground text-sm">Total</p>
                    <p class="text-2xl font-semibold">{{ indicators.total }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-muted-foreground text-sm">Ativas</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.active }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-muted-foreground text-sm">Inativas</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.inactive }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-muted-foreground text-sm">Removidas</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.deleted }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div
            v-if="hasAnyEntities"
            class="flex flex-col gap-3 sm:flex-row sm:items-center"
        >
            <div class="relative sm:max-w-xs sm:flex-1">
                <Search
                    class="text-muted-foreground pointer-events-none absolute left-2.5 top-2.5 size-4"
                />
                <Input
                    v-model="search"
                    placeholder="Buscar por nome ou documento"
                    aria-label="Buscar entidades legais por nome ou documento"
                    class="pl-8"
                />
            </div>

            <select
                v-model="statusFilter"
                aria-label="Filtrar entidades legais por status"
                class="border-input shadow-xs focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 py-1 text-sm outline-none focus-visible:ring-[3px]"
            >
                <option value="all">Todas</option>
                <option value="active">Ativas</option>
                <option value="inactive">Inativas</option>
                <option value="deleted">Excluídas</option>
            </select>

            <label
                class="text-muted-foreground flex items-center gap-2 text-sm"
            >
                <input
                    v-model="onlyPrimary"
                    type="checkbox"
                    class="border-input size-4 rounded"
                />
                Somente principal
            </label>
        </div>

        <EmptyState
            v-if="!hasAnyEntities"
            title="Nenhuma entidade legal cadastrada ainda."
            description="Cadastre a primeira entidade legal da sua clínica para começar."
        >
            <template #action>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Cadastrar primeira entidade legal
                </Button>
            </template>
        </EmptyState>

        <EmptyState
            v-else-if="!hasFilteredResults"
            title="Nenhuma entidade legal corresponde aos filtros informados."
        />

        <template v-else>
            <div class="hidden overflow-x-auto rounded-md border md:block">
                <table class="w-full text-sm">
                    <thead
                        class="bg-muted/50 text-muted-foreground border-b text-left"
                    >
                        <tr>
                            <th class="px-4 py-2 font-medium">
                                Entidade legal
                            </th>
                            <th class="px-4 py-2 font-medium">Documento</th>
                            <th class="px-4 py-2 font-medium">Cidade/UF</th>
                            <th class="px-4 py-2 font-medium">Contato</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 font-medium">
                                <span class="sr-only">Ações</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="entity in filteredEntities"
                            :key="entity.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ entity.legal_name }}
                            </td>
                            <td class="text-muted-foreground px-4 py-3">
                                {{ entity.document }}
                            </td>
                            <td class="text-muted-foreground px-4 py-3">
                                <template v-if="entity.address">
                                    {{ entity.address.city }}/{{
                                        entity.address.state
                                    }}
                                </template>
                                <template v-else>—</template>
                            </td>
                            <td class="text-muted-foreground px-4 py-3">
                                {{ entity.phone || entity.email || '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <StatusBadge
                                        :status="entity.status"
                                        :deleted-at="entity.deleted_at"
                                        :highlight-label="
                                            entity.is_primary
                                                ? 'Principal'
                                                : undefined
                                        "
                                    />
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <LegalEntityRowActions
                                    :legal-entity="entity"
                                    :disabled="processingId === entity.id"
                                    @edit="openEditSheet(entity)"
                                    @toggle-status="toggleStatus(entity)"
                                    @make-primary="makePrimary(entity)"
                                    @delete="entityPendingDeletion = entity"
                                    @restore="restore(entity)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3 md:hidden">
                <Card v-for="entity in filteredEntities" :key="entity.id">
                    <CardContent class="flex flex-col gap-2 py-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-medium">
                                    {{ entity.legal_name }}
                                </p>
                                <p class="text-muted-foreground text-sm">
                                    {{ entity.document }}
                                </p>
                            </div>
                            <LegalEntityRowActions
                                :legal-entity="entity"
                                :disabled="processingId === entity.id"
                                @edit="openEditSheet(entity)"
                                @toggle-status="toggleStatus(entity)"
                                @make-primary="makePrimary(entity)"
                                @delete="entityPendingDeletion = entity"
                                @restore="restore(entity)"
                            />
                        </div>

                        <div class="flex flex-wrap gap-1">
                            <StatusBadge
                                :status="entity.status"
                                :deleted-at="entity.deleted_at"
                                :highlight-label="
                                    entity.is_primary ? 'Principal' : undefined
                                "
                            />
                        </div>

                        <p class="text-muted-foreground text-sm">
                            <template v-if="entity.address"
                                >{{ entity.address.city }}/{{
                                    entity.address.state
                                }}</template
                            >
                            <template v-if="entity.phone || entity.email">
                                {{ entity.address ? ' · ' : ''
                                }}{{ entity.phone || entity.email }}
                            </template>
                        </p>
                    </CardContent>
                </Card>
            </div>
        </template>

        <p
            v-if="hasAnyEntities && hasActiveFilters"
            class="text-muted-foreground text-sm"
        >
            {{ filteredEntities.length }} de {{ indicators.total }} entidades
            legais
        </p>

        <Sheet v-model:open="sheetOpen">
            <SheetContent
                side="right"
                class="w-full gap-0 overflow-y-auto sm:max-w-xl"
            >
                <SheetHeader>
                    <SheetTitle>
                        {{
                            sheetMode === 'create'
                                ? 'Nova entidade legal'
                                : 'Editar entidade legal'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        {{
                            sheetMode === 'create'
                                ? 'Cadastre uma nova entidade legal da organização.'
                                : `Atualize os dados de ${editingEntity?.legal_name ?? 'entidade legal'}.`
                        }}
                    </SheetDescription>
                </SheetHeader>

                <div class="px-4 pb-6">
                    <LegalEntityForm
                        v-if="sheetOpen"
                        :key="editingEntity?.id ?? 'create'"
                        :mode="sheetMode"
                        :legal-entity="editingEntity ?? undefined"
                        :legal-entity-types="legalEntityTypes"
                        :states="states"
                        @success="onFormSuccess"
                        @cancel="sheetOpen = false"
                    />
                </div>
            </SheetContent>
        </Sheet>

        <Dialog
            :open="entityPendingDeletion !== null"
            @update:open="(open) => !open && (entityPendingDeletion = null)"
        >
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Excluir entidade legal?</DialogTitle>
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
                    <Button variant="destructive" @click="confirmDelete">
                        Excluir
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

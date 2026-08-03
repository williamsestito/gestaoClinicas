<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import type { EditableSpecialty } from '@/components/specialties/SpecialtyForm.vue';
import SpecialtyForm from '@/components/specialties/SpecialtyForm.vue';
import SpecialtyRowActions from '@/components/specialties/SpecialtyRowActions.vue';
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
} from '@/routes/settings/specialties';

export type SpecialtyRow = EditableSpecialty & {
    status: 'active' | 'inactive';
    professionals_count: number;
    services_count: number;
    deleted_at: string | null;
    updated_at: string;
};

const props = defineProps<{
    specialties: SpecialtyRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e serviços' },
            { title: 'Especialidades' },
        ],
    },
});

const search = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive' | 'deleted'>('all');
const professionalsFilter = ref<'all' | 'with' | 'without'>('all');
const servicesFilter = ref<'all' | 'with' | 'without'>('all');

const indicators = computed(() => ({
    total: props.specialties.length,
    active: props.specialties.filter(
        (specialty) => !specialty.deleted_at && specialty.status === 'active',
    ).length,
    inactive: props.specialties.filter(
        (specialty) => !specialty.deleted_at && specialty.status === 'inactive',
    ).length,
    deleted: props.specialties.filter((specialty) => specialty.deleted_at)
        .length,
}));

const filteredSpecialties = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.specialties.filter((specialty) => {
        const matchesSearch =
            term === '' ||
            specialty.name.toLowerCase().includes(term) ||
            (specialty.code ?? '').toLowerCase().includes(term);

        // "Todas" nunca inclui excluídas — registros excluídos só aparecem
        // com o filtro "Excluídas" explicitamente selecionado.
        const matchesStatus =
            statusFilter.value === 'all'
                ? !specialty.deleted_at
                : statusFilter.value === 'deleted'
                  ? Boolean(specialty.deleted_at)
                  : !specialty.deleted_at &&
                    specialty.status === statusFilter.value;

        const matchesProfessionals =
            professionalsFilter.value === 'all' ||
            (professionalsFilter.value === 'with'
                ? specialty.professionals_count > 0
                : specialty.professionals_count === 0);

        const matchesServices =
            servicesFilter.value === 'all' ||
            (servicesFilter.value === 'with'
                ? specialty.services_count > 0
                : specialty.services_count === 0);

        return (
            matchesSearch &&
            matchesStatus &&
            matchesProfessionals &&
            matchesServices
        );
    });
});

const hasAny = computed(() => props.specialties.length > 0);
const hasFilteredResults = computed(() => filteredSpecialties.value.length > 0);
const hasActiveFilters = computed(
    () =>
        search.value.trim() !== '' ||
        statusFilter.value !== 'all' ||
        professionalsFilter.value !== 'all' ||
        servicesFilter.value !== 'all',
);

const sheetOpen = ref(false);
const sheetMode = ref<'create' | 'edit'>('create');
const editingSpecialty = ref<SpecialtyRow | null>(null);
const processingId = ref<string | null>(null);
const pendingDeletion = ref<SpecialtyRow | null>(null);

function openCreateSheet() {
    sheetMode.value = 'create';
    editingSpecialty.value = null;
    sheetOpen.value = true;
}

function openEditSheet(specialty: SpecialtyRow) {
    sheetMode.value = 'edit';
    editingSpecialty.value = specialty;
    sheetOpen.value = true;
}

function onFormSuccess() {
    sheetOpen.value = false;
}

function toggleActivate(specialty: SpecialtyRow) {
    processingId.value = specialty.id;
    const routeFn = specialty.status === 'active' ? deactivate : activate;
    router.patch(
        routeFn(specialty.id).url,
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

function restore(specialty: SpecialtyRow) {
    processingId.value = specialty.id;
    router.post(
        restoreRoute(specialty.id).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function formatDate(value: string): string {
    return formatDateTimeBr(value);
}
</script>

<template>
    <Head title="Especialidades" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Especialidades"
            description="Cadastro operacional das especialidades atendidas pela clínica — usado para organizar profissionais e serviços."
        >
            <template #actions>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Nova especialidade
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
                    <p class="text-sm text-muted-foreground">Ativas</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.active }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">Inativas</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.inactive }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">Excluídas</p>
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
                    placeholder="Buscar por nome ou código"
                    aria-label="Buscar especialidades por nome ou código"
                    class="pl-8"
                />
            </div>

            <select
                v-model="statusFilter"
                aria-label="Filtrar especialidades por status"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Todas</option>
                <option value="active">Ativas</option>
                <option value="inactive">Inativas</option>
                <option value="deleted">Excluídas</option>
            </select>

            <select
                v-model="professionalsFilter"
                aria-label="Filtrar especialidades por vínculo com profissionais"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Profissionais: todas</option>
                <option value="with">Com profissionais vinculados</option>
                <option value="without">Sem profissionais vinculados</option>
            </select>

            <select
                v-model="servicesFilter"
                aria-label="Filtrar especialidades por vínculo com serviços"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Serviços: todas</option>
                <option value="with">Com serviços vinculados</option>
                <option value="without">Sem serviços vinculados</option>
            </select>
        </div>

        <EmptyState
            v-if="!hasAny"
            title="Nenhuma especialidade cadastrada ainda."
            description="Cadastre a primeira especialidade da sua clínica para começar."
        >
            <template #action>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Cadastrar primeira especialidade
                </Button>
            </template>
        </EmptyState>

        <EmptyState
            v-else-if="!hasFilteredResults"
            title="Nenhuma especialidade corresponde aos filtros informados."
        />

        <template v-else>
            <div class="hidden overflow-x-auto rounded-md border md:block">
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/50 text-left text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-2 font-medium">Especialidade</th>
                            <th class="px-4 py-2 font-medium">Código</th>
                            <th class="px-4 py-2 font-medium">Profissionais</th>
                            <th class="px-4 py-2 font-medium">Serviços</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 font-medium">Atualizado em</th>
                            <th class="px-4 py-2 font-medium">
                                <span class="sr-only">Ações</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="specialty in filteredSpecialties"
                            :key="specialty.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ specialty.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ specialty.code || '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ specialty.professionals_count }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ specialty.services_count }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :status="specialty.status"
                                    :deleted-at="specialty.deleted_at"
                                />
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ formatDate(specialty.updated_at) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <SpecialtyRowActions
                                    :specialty="specialty"
                                    :disabled="processingId === specialty.id"
                                    @edit="openEditSheet(specialty)"
                                    @activate="toggleActivate(specialty)"
                                    @deactivate="toggleActivate(specialty)"
                                    @delete="pendingDeletion = specialty"
                                    @restore="restore(specialty)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3 md:hidden">
                <Card
                    v-for="specialty in filteredSpecialties"
                    :key="specialty.id"
                >
                    <CardContent class="flex flex-col gap-2 py-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-medium">{{ specialty.name }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ specialty.code || 'Sem código' }}
                                </p>
                            </div>
                            <SpecialtyRowActions
                                :specialty="specialty"
                                :disabled="processingId === specialty.id"
                                @edit="openEditSheet(specialty)"
                                @activate="toggleActivate(specialty)"
                                @deactivate="toggleActivate(specialty)"
                                @delete="pendingDeletion = specialty"
                                @restore="restore(specialty)"
                            />
                        </div>

                        <StatusBadge
                            :status="specialty.status"
                            :deleted-at="specialty.deleted_at"
                        />

                        <p class="text-sm text-muted-foreground">
                            {{ specialty.professionals_count }} profissional(is)
                            · {{ specialty.services_count }} serviço(s)
                        </p>
                    </CardContent>
                </Card>
            </div>
        </template>

        <p
            v-if="hasAny && hasActiveFilters"
            class="text-sm text-muted-foreground"
        >
            {{ filteredSpecialties.length }} de {{ indicators.total }}
            especialidades
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
                                ? 'Nova especialidade'
                                : 'Editar especialidade'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        {{
                            sheetMode === 'create'
                                ? 'Cadastre uma nova especialidade da clínica.'
                                : `Atualize os dados de ${editingSpecialty?.name ?? 'especialidade'}.`
                        }}
                    </SheetDescription>
                </SheetHeader>

                <div class="px-4 pb-6">
                    <SpecialtyForm
                        v-if="sheetOpen"
                        :key="editingSpecialty?.id ?? 'create'"
                        :mode="sheetMode"
                        :specialty="editingSpecialty ?? undefined"
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
                    <DialogTitle>Excluir especialidade?</DialogTitle>
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

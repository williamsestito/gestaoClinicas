<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import type {
    ServiceOption,
    UnitOption,
} from '@/components/services/ServiceForm.vue';
import ServiceForm from '@/components/services/ServiceForm.vue';
import ServiceRowActions from '@/components/services/ServiceRowActions.vue';
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
import { formatCurrencyBrl } from '@/lib/masks';
import { dashboard } from '@/routes';
import {
    activate,
    deactivate,
    destroy,
    edit as editRoute,
    restore as restoreRoute,
} from '@/routes/settings/services';

export type ServiceRow = {
    id: string;
    name: string;
    code: string;
    default_duration_minutes: number;
    default_price_cents: number | null;
    status: 'active' | 'inactive';
    is_public: boolean;
    unit_availability_scope: string;
    specialty_ids: string[];
    specialties: string[];
    unit_ids: string[];
    has_available_unit: boolean;
    professionals_count: number;
    deleted_at: string | null;
    updated_at: string;
};

const props = defineProps<{
    services: ServiceRow[];
    specialties?: ServiceOption[];
    units?: UnitOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e serviços' },
            { title: 'Serviços e procedimentos' },
        ],
    },
});

const specialtyOptions = computed(() => props.specialties ?? []);
const unitOptions = computed(() => props.units ?? []);

const search = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive' | 'deleted'>('all');
const specialtyFilter = ref('all');
const unitFilter = ref('all');
const professionalsFilter = ref<'all' | 'with' | 'without'>('all');
const publicFilter = ref<'all' | 'public' | 'private'>('all');
const availabilityFilter = ref<'all' | 'available' | 'unavailable'>('all');

const indicators = computed(() => ({
    total: props.services.length,
    active: props.services.filter(
        (service) => !service.deleted_at && service.status === 'active',
    ).length,
    inactive: props.services.filter(
        (service) => !service.deleted_at && service.status === 'inactive',
    ).length,
    deleted: props.services.filter((service) => service.deleted_at).length,
}));

const filteredServices = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.services.filter((service) => {
        const matchesSearch =
            term === '' ||
            service.name.toLowerCase().includes(term) ||
            service.code.toLowerCase().includes(term);

        const matchesStatus =
            statusFilter.value === 'all'
                ? !service.deleted_at
                : statusFilter.value === 'deleted'
                  ? Boolean(service.deleted_at)
                  : !service.deleted_at &&
                    service.status === statusFilter.value;

        const matchesSpecialty =
            specialtyFilter.value === 'all' ||
            service.specialty_ids.includes(specialtyFilter.value);

        const matchesUnit =
            unitFilter.value === 'all' ||
            service.unit_ids.includes(unitFilter.value);

        const matchesProfessionals =
            professionalsFilter.value === 'all' ||
            (professionalsFilter.value === 'with'
                ? service.professionals_count > 0
                : service.professionals_count === 0);

        const matchesPublic =
            publicFilter.value === 'all' ||
            (publicFilter.value === 'public'
                ? service.is_public
                : !service.is_public);

        const matchesAvailability =
            availabilityFilter.value === 'all' ||
            (availabilityFilter.value === 'available'
                ? service.has_available_unit
                : !service.has_available_unit);

        return (
            matchesSearch &&
            matchesStatus &&
            matchesSpecialty &&
            matchesUnit &&
            matchesProfessionals &&
            matchesPublic &&
            matchesAvailability
        );
    });
});

const hasAny = computed(() => props.services.length > 0);
const hasFilteredResults = computed(() => filteredServices.value.length > 0);
const hasActiveFilters = computed(
    () =>
        search.value.trim() !== '' ||
        statusFilter.value !== 'all' ||
        specialtyFilter.value !== 'all' ||
        unitFilter.value !== 'all' ||
        professionalsFilter.value !== 'all' ||
        publicFilter.value !== 'all' ||
        availabilityFilter.value !== 'all',
);

const sheetOpen = ref(false);
const processingId = ref<string | null>(null);
const pendingDeletion = ref<ServiceRow | null>(null);

function openCreateSheet() {
    sheetOpen.value = true;
}

function openEditSheet(service: ServiceRow) {
    router.get(editRoute(service.id).url);
}

function onFormSuccess() {
    sheetOpen.value = false;
}

function toggleActivate(service: ServiceRow) {
    processingId.value = service.id;
    const routeFn = service.status === 'active' ? deactivate : activate;
    router.patch(
        routeFn(service.id).url,
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

function restore(service: ServiceRow) {
    processingId.value = service.id;
    router.post(
        restoreRoute(service.id).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function formatPrice(cents: number | null): string {
    if (cents === null) {
        return '—';
    }

    return formatCurrencyBrl(cents);
}

function formatDuration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;

    if (hours === 0) {
        return `${remaining}min`;
    }

    return remaining === 0 ? `${hours}h` : `${hours}h${remaining}min`;
}
</script>

<template>
    <Head title="Serviços e procedimentos" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Serviços e procedimentos"
            description="Cadastro operacional dos serviços oferecidos pela clínica — usado pela agenda e pelo financeiro nas próximas etapas. O conteúdo público do site continua sendo gerenciado em 'Site da clínica'."
        >
            <template #actions>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Novo serviço
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
                    placeholder="Buscar por nome ou código"
                    aria-label="Buscar serviços por nome ou código"
                    class="pl-8"
                />
            </div>

            <select
                v-model="statusFilter"
                aria-label="Filtrar serviços por status"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Todos</option>
                <option value="active">Ativos</option>
                <option value="inactive">Inativos</option>
                <option value="deleted">Excluídos</option>
            </select>

            <select
                v-model="specialtyFilter"
                aria-label="Filtrar serviços por especialidade"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Todas as especialidades</option>
                <option
                    v-for="specialty in specialtyOptions"
                    :key="specialty.id"
                    :value="specialty.id"
                >
                    {{ specialty.name }}
                </option>
            </select>

            <select
                v-model="unitFilter"
                aria-label="Filtrar serviços por unidade"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Todas as unidades</option>
                <option
                    v-for="unit in unitOptions"
                    :key="unit.id"
                    :value="unit.id"
                >
                    {{ unit.name }}
                </option>
            </select>

            <select
                v-model="professionalsFilter"
                aria-label="Filtrar serviços por vínculo com profissionais"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Profissionais: todos</option>
                <option value="with">Com profissionais vinculados</option>
                <option value="without">Sem profissionais vinculados</option>
            </select>

            <select
                v-model="publicFilter"
                aria-label="Filtrar serviços por exibição pública"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Exibição pública: todos</option>
                <option value="public">Exibição pública ativada</option>
                <option value="private">Exibição pública desativada</option>
            </select>

            <select
                v-model="availabilityFilter"
                aria-label="Filtrar serviços por disponibilidade operacional"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="all">Disponibilidade: todas</option>
                <option value="available">Disponível em alguma unidade</option>
                <option value="unavailable">Sem unidade disponível</option>
            </select>
        </div>

        <EmptyState
            v-if="!hasAny"
            title="Nenhum serviço cadastrado ainda."
            description="Cadastre o primeiro serviço ou procedimento da sua clínica para começar."
        >
            <template #action>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Cadastrar primeiro serviço
                </Button>
            </template>
        </EmptyState>

        <EmptyState
            v-else-if="!hasFilteredResults"
            title="Nenhum serviço corresponde aos filtros informados."
        />

        <template v-else>
            <div class="hidden overflow-x-auto rounded-md border md:block">
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/50 text-left text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-2 font-medium">Serviço</th>
                            <th class="px-4 py-2 font-medium">Código</th>
                            <th class="px-4 py-2 font-medium">Duração</th>
                            <th class="px-4 py-2 font-medium">Preço</th>
                            <th class="px-4 py-2 font-medium">
                                Especialidades
                            </th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 font-medium">
                                <span class="sr-only">Ações</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="service in filteredServices"
                            :key="service.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ service.name }}
                                <span
                                    v-if="service.is_public"
                                    class="ml-1 text-xs text-muted-foreground"
                                    >(exibição pública futura)</span
                                >
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ service.code }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{
                                    formatDuration(
                                        service.default_duration_minutes,
                                    )
                                }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ formatPrice(service.default_price_cents) }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ service.specialties.join(', ') || '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :status="service.status"
                                    :deleted-at="service.deleted_at"
                                />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <ServiceRowActions
                                    :service="service"
                                    :disabled="processingId === service.id"
                                    @edit="openEditSheet(service)"
                                    @activate="toggleActivate(service)"
                                    @deactivate="toggleActivate(service)"
                                    @delete="pendingDeletion = service"
                                    @restore="restore(service)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3 md:hidden">
                <Card v-for="service in filteredServices" :key="service.id">
                    <CardContent class="flex flex-col gap-2 py-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-medium">{{ service.name }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ service.code }}
                                </p>
                            </div>
                            <ServiceRowActions
                                :service="service"
                                :disabled="processingId === service.id"
                                @edit="openEditSheet(service)"
                                @activate="toggleActivate(service)"
                                @deactivate="toggleActivate(service)"
                                @delete="pendingDeletion = service"
                                @restore="restore(service)"
                            />
                        </div>

                        <StatusBadge
                            :status="service.status"
                            :deleted-at="service.deleted_at"
                        />

                        <p class="text-sm text-muted-foreground">
                            {{
                                formatDuration(service.default_duration_minutes)
                            }}
                            · {{ formatPrice(service.default_price_cents) }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </template>

        <p
            v-if="hasAny && hasActiveFilters"
            class="text-sm text-muted-foreground"
        >
            {{ filteredServices.length }} de {{ indicators.total }} serviços
        </p>

        <Sheet v-model:open="sheetOpen">
            <SheetContent
                side="right"
                class="w-full gap-0 overflow-y-auto sm:max-w-2xl"
            >
                <SheetHeader>
                    <SheetTitle>Novo serviço</SheetTitle>
                    <SheetDescription>
                        Cadastre um novo serviço ou procedimento da clínica.
                    </SheetDescription>
                </SheetHeader>

                <div class="px-4 pb-6">
                    <ServiceForm
                        v-if="sheetOpen"
                        mode="create"
                        :specialties="specialtyOptions"
                        :units="unitOptions"
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
                    <DialogTitle>Excluir serviço?</DialogTitle>
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

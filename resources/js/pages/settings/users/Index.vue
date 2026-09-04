<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, Copy, Plus, Search } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
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
import type {
    EditableMembership,
    RoleOption,
    UnitOption,
} from '@/components/users/UserForm.vue';
import UserForm from '@/components/users/UserForm.vue';
import UserRowActions from '@/components/users/UserRowActions.vue';
import { dashboard } from '@/routes';
import { cancel, resend } from '@/routes/settings/invitations';
import { activate, deactivate } from '@/routes/settings/users';

type Membership = Omit<EditableMembership, 'unit_memberships'> & {
    user: {
        name: string;
        email: string;
        phone: string | null;
        photo_url: string | null;
        is_active: boolean;
        last_login_at: string | null;
    };
    role: { id: string; name: string } | null;
    unit_memberships: {
        unit_id: string;
        is_primary: boolean;
        unit: { id: string; name: string } | null;
    }[];
};

type Invitation = {
    id: string;
    email: string;
    status: 'pending' | 'accepted' | 'cancelled' | 'expired';
    role: { name: string } | null;
    created_at: string;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e acessos' },
            { title: 'Usuários' },
        ],
    },
});

const props = defineProps<{
    memberships: Membership[];
    invitations: Invitation[];
    roles: RoleOption[];
    units: UnitOption[];
}>();

const search = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive'>('all');
const roleFilter = ref<'all' | string>('all');

function primaryUnitName(membership: Membership): string {
    return (
        membership.unit_memberships.find((um) => um.is_primary)?.unit?.name ??
        '—'
    );
}

const indicators = computed(() => ({
    total: props.memberships.length,
    active: props.memberships.filter((m) => m.user.is_active).length,
    inactive: props.memberships.filter((m) => !m.user.is_active).length,
    pendingInvitations: props.invitations.filter((i) => i.status === 'pending')
        .length,
    admins: props.memberships.filter(
        (m) => m.role?.name === 'Administrador da clínica',
    ).length,
    allUnits: props.memberships.filter(
        (m) =>
            m.unit_memberships.length === props.units.length &&
            props.units.length > 0,
    ).length,
}));

const filteredMemberships = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.memberships.filter((m) => {
        const matchesSearch =
            term === '' ||
            m.user.name.toLowerCase().includes(term) ||
            m.user.email.toLowerCase().includes(term);

        const matchesStatus =
            statusFilter.value === 'all'
                ? true
                : statusFilter.value === 'active'
                  ? m.user.is_active
                  : !m.user.is_active;

        const matchesRole =
            roleFilter.value === 'all' || m.role?.id === roleFilter.value;

        return matchesSearch && matchesStatus && matchesRole;
    });
});

const hasActiveFilters = computed(
    () =>
        search.value.trim() !== '' ||
        statusFilter.value !== 'all' ||
        roleFilter.value !== 'all',
);
const hasFilteredResults = computed(() => filteredMemberships.value.length > 0);

const hasAnyMemberships = computed(() => props.memberships.length > 0);

const sheetOpen = ref(false);
const sheetMode = ref<'invite' | 'edit'>('invite');
const editingMembership = ref<Membership | null>(null);
const processingId = ref<string | null>(null);

function openInviteSheet() {
    sheetMode.value = 'invite';
    editingMembership.value = null;
    sheetOpen.value = true;
}

function openEditSheet(membership: Membership) {
    sheetMode.value = 'edit';
    editingMembership.value = membership;
    sheetOpen.value = true;
}

function onFormSuccess() {
    sheetOpen.value = false;
}

// O token bruto do convite só existe em memória na resposta de criação
// (o backend guarda apenas o hash) — por isso ele chega aqui via "flash"
// (não como prop normal, para nunca ficar preso no histórico de navegação)
// e só pode ser copiado agora; depois de fechar este diálogo, não há como
// recuperá-lo — é preciso reenviar o convite.
const inviteLink = ref<{ email: string; url: string } | null>(null);
const linkCopied = ref(false);

let stopFlashListener: (() => void) | undefined;

onMounted(() => {
    stopFlashListener = router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash as
            { inviteLink?: { email: string; url: string } } | undefined;

        if (flash?.inviteLink) {
            inviteLink.value = flash.inviteLink;
            linkCopied.value = false;
        }
    });
});

onUnmounted(() => {
    stopFlashListener?.();
});

async function copyInviteLink() {
    if (!inviteLink.value) {
        return;
    }

    await navigator.clipboard.writeText(inviteLink.value.url);
    linkCopied.value = true;
    toast.success('Link copiado!');
}

function toggleStatus(membership: Membership) {
    processingId.value = membership.id;
    const action = membership.user.is_active ? deactivate : activate;
    router.patch(
        action(membership.id).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function cancelInvitation(invitation: Invitation) {
    processingId.value = invitation.id;
    router.post(
        cancel(invitation.id).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function resendInvitation(invitation: Invitation) {
    processingId.value = invitation.id;
    router.post(
        resend(invitation.id).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}
</script>

<template>
    <Head title="Usuários" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Usuários"
            description="Gerencie quem tem acesso à clínica e o que cada pessoa pode fazer."
        >
            <template #actions>
                <Button @click="openInviteSheet">
                    <Plus class="size-4" />
                    Convidar usuário
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="hasAnyMemberships"
            class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
        >
            <Card>
                <CardContent class="py-4">
                    <p class="text-muted-foreground text-sm">Total</p>
                    <p class="text-2xl font-semibold">{{ indicators.total }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-muted-foreground text-sm">Ativos</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.active }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-muted-foreground text-sm">Inativos</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.inactive }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-muted-foreground text-sm">
                        Convites pendentes
                    </p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.pendingInvitations }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-muted-foreground text-sm">Administradores</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.admins }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-muted-foreground text-sm">
                        Todas as unidades
                    </p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.allUnits }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div
            v-if="hasAnyMemberships"
            class="flex flex-col gap-3 sm:flex-row sm:items-center"
        >
            <div class="relative sm:max-w-xs sm:flex-1">
                <Search
                    class="text-muted-foreground pointer-events-none absolute left-2.5 top-2.5 size-4"
                />
                <Input
                    v-model="search"
                    placeholder="Buscar por nome ou e-mail"
                    aria-label="Buscar usuários por nome ou e-mail"
                    class="pl-8"
                />
            </div>

            <select
                v-model="statusFilter"
                aria-label="Filtrar usuários por status"
                class="border-input shadow-xs focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 py-1 text-sm outline-none focus-visible:ring-[3px]"
            >
                <option value="all">Todos os status</option>
                <option value="active">Ativos</option>
                <option value="inactive">Inativos</option>
            </select>

            <select
                v-model="roleFilter"
                aria-label="Filtrar usuários por papel"
                class="border-input shadow-xs focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 py-1 text-sm outline-none focus-visible:ring-[3px]"
            >
                <option value="all">Todos os papéis</option>
                <option v-for="role in roles" :key="role.id" :value="role.id">
                    {{ role.name }}
                </option>
            </select>
        </div>

        <EmptyState
            v-if="!hasAnyMemberships"
            title="Nenhum usuário cadastrado ainda."
            description="Convide a primeira pessoa da sua equipe."
        >
            <template #action>
                <Button @click="openInviteSheet">
                    <Plus class="size-4" />
                    Convidar usuário
                </Button>
            </template>
        </EmptyState>

        <EmptyState
            v-else-if="!hasFilteredResults"
            title="Nenhum usuário corresponde aos filtros informados."
        />

        <template v-else>
            <div class="hidden overflow-x-auto rounded-md border md:block">
                <table class="w-full text-sm">
                    <thead
                        class="bg-muted/50 text-muted-foreground border-b text-left"
                    >
                        <tr>
                            <th class="px-4 py-2 font-medium">
                                <span class="sr-only">Foto</span>
                            </th>
                            <th class="px-4 py-2 font-medium">Nome</th>
                            <th class="px-4 py-2 font-medium">E-mail</th>
                            <th class="px-4 py-2 font-medium">Telefone</th>
                            <th class="px-4 py-2 font-medium">Papel</th>
                            <th class="px-4 py-2 font-medium">
                                Unidade principal
                            </th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 font-medium">Último acesso</th>
                            <th class="px-4 py-2 font-medium">
                                <span class="sr-only">Ações</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="membership in filteredMemberships"
                            :key="membership.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-3">
                                <Avatar class="size-8">
                                    <AvatarImage
                                        v-if="membership.user.photo_url"
                                        :src="membership.user.photo_url"
                                        :alt="membership.user.name"
                                    />
                                    <AvatarFallback>
                                        {{ membership.user.name.charAt(0) }}
                                    </AvatarFallback>
                                </Avatar>
                            </td>
                            <td class="px-4 py-3 font-medium">
                                {{ membership.user.name }}
                            </td>
                            <td class="text-muted-foreground px-4 py-3">
                                {{ membership.user.email }}
                            </td>
                            <td class="text-muted-foreground px-4 py-3">
                                {{ membership.user.phone || '—' }}
                            </td>
                            <td class="text-muted-foreground px-4 py-3">
                                {{ membership.role?.name ?? '—' }}
                            </td>
                            <td class="text-muted-foreground px-4 py-3">
                                {{ primaryUnitName(membership) }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        membership.user.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        membership.user.is_active
                                            ? 'Ativo'
                                            : 'Inativo'
                                    }}
                                </Badge>
                            </td>
                            <td class="text-muted-foreground px-4 py-3">
                                {{
                                    membership.user.last_login_at ??
                                    'nunca acessou'
                                }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <UserRowActions
                                    :membership="membership"
                                    :disabled="processingId === membership.id"
                                    @edit="openEditSheet(membership)"
                                    @toggle-status="toggleStatus(membership)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3 md:hidden">
                <Card
                    v-for="membership in filteredMemberships"
                    :key="membership.id"
                >
                    <CardContent
                        class="flex items-center justify-between gap-3 py-4"
                    >
                        <div class="flex items-center gap-3">
                            <Avatar class="size-10">
                                <AvatarImage
                                    v-if="membership.user.photo_url"
                                    :src="membership.user.photo_url"
                                    :alt="membership.user.name"
                                />
                                <AvatarFallback>
                                    {{ membership.user.name.charAt(0) }}
                                </AvatarFallback>
                            </Avatar>
                            <div>
                                <p class="font-medium">
                                    {{ membership.user.name }}
                                </p>
                                <p class="text-muted-foreground text-sm">
                                    {{ membership.user.email }}
                                </p>
                                <div class="mt-2 flex flex-wrap gap-1">
                                    <Badge
                                        :variant="
                                            membership.user.is_active
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{
                                            membership.user.is_active
                                                ? 'Ativo'
                                                : 'Inativo'
                                        }}
                                    </Badge>
                                    <Badge
                                        v-if="membership.role"
                                        variant="outline"
                                    >
                                        {{ membership.role.name }}
                                    </Badge>
                                </div>
                                <p class="text-muted-foreground mt-1 text-xs">
                                    Último acesso:
                                    {{
                                        membership.user.last_login_at ??
                                        'nunca acessou'
                                    }}
                                </p>
                            </div>
                        </div>
                        <UserRowActions
                            :membership="membership"
                            :disabled="processingId === membership.id"
                            @edit="openEditSheet(membership)"
                            @toggle-status="toggleStatus(membership)"
                        />
                    </CardContent>
                </Card>
            </div>
        </template>

        <p
            v-if="hasAnyMemberships && hasActiveFilters"
            class="text-muted-foreground text-sm"
        >
            {{ filteredMemberships.length }} de {{ indicators.total }} usuários
        </p>

        <template v-if="invitations.length > 0">
            <h2 class="text-lg font-semibold">Convites</h2>
            <div class="grid gap-3">
                <Card v-for="invitation in invitations" :key="invitation.id">
                    <CardContent
                        class="flex items-center justify-between gap-3 py-4"
                    >
                        <div>
                            <p class="font-medium">{{ invitation.email }}</p>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <Badge variant="secondary">
                                    {{
                                        invitation.status === 'expired'
                                            ? 'Expirado'
                                            : 'Pendente'
                                    }}
                                </Badge>
                                <Badge v-if="invitation.role" variant="outline">
                                    {{ invitation.role.name }}
                                </Badge>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                size="sm"
                                variant="secondary"
                                :disabled="processingId === invitation.id"
                                @click="resendInvitation(invitation)"
                            >
                                Reenviar
                            </Button>
                            <Button
                                size="sm"
                                variant="secondary"
                                :disabled="processingId === invitation.id"
                                @click="cancelInvitation(invitation)"
                            >
                                Cancelar
                            </Button>
                        </div>
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
                            sheetMode === 'invite'
                                ? 'Convidar usuário'
                                : 'Editar usuário'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        {{
                            sheetMode === 'invite'
                                ? 'Um e-mail de convite será enviado para que a pessoa defina sua própria senha.'
                                : `Atualize o papel e as unidades de ${editingMembership?.user.name ?? ''}.`
                        }}
                    </SheetDescription>
                </SheetHeader>

                <div class="px-4 pb-6">
                    <UserForm
                        v-if="sheetOpen"
                        :key="editingMembership?.id ?? 'invite'"
                        :mode="sheetMode"
                        :membership="editingMembership ?? undefined"
                        :roles="roles"
                        :units="units"
                        @success="onFormSuccess"
                        @cancel="sheetOpen = false"
                    />
                </div>
            </SheetContent>
        </Sheet>

        <Dialog
            :open="inviteLink !== null"
            @update:open="(open) => !open && (inviteLink = null)"
        >
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Convite criado</DialogTitle>
                    <DialogDescription>
                        Enviamos um e-mail para {{ inviteLink?.email }}, mas
                        você também pode copiar o link abaixo e enviar
                        diretamente por WhatsApp ou pessoalmente. A pessoa
                        define a própria senha ao abrir o link.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex items-center gap-2">
                    <Input :model-value="inviteLink?.url" readonly />
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        aria-label="Copiar link do convite"
                        @click="copyInviteLink"
                    >
                        <Check v-if="linkCopied" class="size-4" />
                        <Copy v-else class="size-4" />
                    </Button>
                </div>
                <DialogFooter>
                    <Button type="button" @click="inviteLink = null">
                        Fechar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

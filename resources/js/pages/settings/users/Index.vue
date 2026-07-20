<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

type Membership = EditableMembership & {
    user: {
        name: string;
        email: string;
        is_active: boolean;
        last_login_at: string | null;
    };
    role: { id: string; name: string } | null;
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

    if (term === '') {
        return props.memberships;
    }

    return props.memberships.filter(
        (m) =>
            m.user.name.toLowerCase().includes(term) ||
            m.user.email.toLowerCase().includes(term),
    );
});

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
                    <p class="text-sm text-muted-foreground">
                        Convites pendentes
                    </p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.pendingInvitations }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">Administradores</p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.admins }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">
                        Todas as unidades
                    </p>
                    <p class="text-2xl font-semibold">
                        {{ indicators.allUnits }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div v-if="hasAnyMemberships" class="relative sm:max-w-xs">
            <Search
                class="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
            />
            <Input
                v-model="search"
                placeholder="Buscar por nome ou e-mail"
                aria-label="Buscar usuários por nome ou e-mail"
                class="pl-8"
            />
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

        <div v-else class="grid gap-3">
            <Card
                v-for="membership in filteredMemberships"
                :key="membership.id"
            >
                <CardContent
                    class="flex items-center justify-between gap-3 py-4"
                >
                    <div>
                        <p class="font-medium">{{ membership.user.name }}</p>
                        <p class="text-sm text-muted-foreground">
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
                            <Badge v-if="membership.role" variant="outline">
                                {{ membership.role.name }}
                            </Badge>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Último acesso:
                            {{
                                membership.user.last_login_at ?? 'nunca acessou'
                            }}
                        </p>
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
    </div>
</template>

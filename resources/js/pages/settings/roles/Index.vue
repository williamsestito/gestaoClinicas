<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Copy, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import type {
    EditableRole,
    PermissionOption,
} from '@/components/roles/RoleForm.vue';
import RoleForm from '@/components/roles/RoleForm.vue';
import { Badge } from '@/components/ui/badge';
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { dashboard } from '@/routes';
import { destroy, duplicate } from '@/routes/settings/roles';

const OWNER_ROLE_SLUG = 'proprietario';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Equipe e acessos' },
            { title: 'Perfis e permissões' },
        ],
    },
});

const props = defineProps<{
    roles: (EditableRole & { organization_memberships_count: number })[];
    permissions: Record<string, PermissionOption[]>;
}>();

const sheetOpen = ref(false);
const sheetMode = ref<'create' | 'edit'>('create');
const editingRole = ref<EditableRole | null>(null);
const rolePendingDeletion = ref<EditableRole | null>(null);
const processingRoleId = ref<string | null>(null);

const isOwnerRole = (role: { slug: string }) => role.slug === OWNER_ROLE_SLUG;

function openCreateSheet() {
    sheetMode.value = 'create';
    editingRole.value = null;
    sheetOpen.value = true;
}

function openEditSheet(role: EditableRole) {
    sheetMode.value = 'edit';
    editingRole.value = role;
    sheetOpen.value = true;
}

function onFormSuccess() {
    sheetOpen.value = false;
}

function duplicateRole(role: EditableRole) {
    processingRoleId.value = role.id;
    router.post(
        duplicate(role.id).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => (processingRoleId.value = null),
        },
    );
}

function confirmDelete() {
    if (!rolePendingDeletion.value) {
        return;
    }

    const id = rolePendingDeletion.value.id;
    processingRoleId.value = id;
    router.delete(destroy(id).url, {
        preserveScroll: true,
        onFinish: () => {
            processingRoleId.value = null;
            rolePendingDeletion.value = null;
        },
    });
}

const hasAnyRoles = computed(() => props.roles.length > 0);
</script>

<template>
    <Head title="Perfis e permissões" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Perfis e permissões"
            description="Gerencie os papéis disponíveis na clínica e o que cada um pode acessar."
        >
            <template #actions>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Novo papel
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="!hasAnyRoles"
            title="Nenhum papel cadastrado ainda."
            description="Crie o primeiro papel personalizado da sua clínica."
        >
            <template #action>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Criar primeiro papel
                </Button>
            </template>
        </EmptyState>

        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="role in roles" :key="role.id">
                <CardContent class="flex flex-col gap-3 py-4">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-medium">{{ role.name }}</p>
                            <p
                                v-if="role.description"
                                class="text-sm text-muted-foreground"
                            >
                                {{ role.description }}
                            </p>
                        </div>
                        <Badge v-if="role.is_system" variant="secondary">
                            Sistema
                        </Badge>
                    </div>

                    <p class="text-sm text-muted-foreground">
                        {{ role.permissions.length }} permissões ·
                        {{ role.organization_memberships_count }} usuário(s)
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant="secondary"
                            :disabled="processingRoleId === role.id"
                            @click="openEditSheet(role)"
                        >
                            <Pencil class="size-3.5" />
                            {{ isOwnerRole(role) ? 'Ver' : 'Editar' }}
                        </Button>
                        <Button
                            size="sm"
                            variant="secondary"
                            :disabled="processingRoleId === role.id"
                            @click="duplicateRole(role)"
                        >
                            <Copy class="size-3.5" />
                            Duplicar
                        </Button>
                        <Button
                            v-if="!role.is_system"
                            size="sm"
                            variant="secondary"
                            :disabled="processingRoleId === role.id"
                            @click="rolePendingDeletion = role"
                        >
                            <Trash2 class="size-3.5" />
                            Excluir
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Sheet v-model:open="sheetOpen">
            <SheetContent
                side="right"
                class="w-full gap-0 overflow-y-auto sm:max-w-xl"
            >
                <SheetHeader>
                    <SheetTitle>
                        {{ sheetMode === 'create' ? 'Novo papel' : 'Papel' }}
                    </SheetTitle>
                    <SheetDescription>
                        {{
                            sheetMode === 'create'
                                ? 'Crie um papel personalizado e escolha suas permissões.'
                                : `Detalhes do papel ${editingRole?.name ?? ''}.`
                        }}
                    </SheetDescription>
                </SheetHeader>

                <div class="px-4 pb-6">
                    <RoleForm
                        v-if="sheetOpen"
                        :key="editingRole?.id ?? 'create'"
                        :mode="sheetMode"
                        :role="editingRole ?? undefined"
                        :permission-groups="permissions"
                        :read-only="
                            sheetMode === 'edit' &&
                            editingRole !== null &&
                            isOwnerRole(editingRole)
                        "
                        @success="onFormSuccess"
                        @cancel="sheetOpen = false"
                    />
                </div>
            </SheetContent>
        </Sheet>

        <Dialog
            :open="rolePendingDeletion !== null"
            @update:open="(open) => !open && (rolePendingDeletion = null)"
        >
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Excluir papel?</DialogTitle>
                    <DialogDescription>
                        Usuários com este papel perderão as permissões
                        associadas a ele, mas continuarão com acesso à clínica.
                        Esta ação não pode ser desfeita.
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

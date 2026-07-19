<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
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
import { dashboard } from '@/routes';
import {
    create,
    destroy,
    edit,
    primary as primaryRoute,
    restore as restoreRoute,
    status as statusRoute,
} from '@/routes/settings/legal-entities';
import type { LegalEntity } from '@/types/organization';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Configurações da clínica' },
            { title: 'Entidades legais' },
        ],
    },
});

defineProps<{
    legalEntities: LegalEntity[];
}>();

const pendingDeletion = ref<LegalEntity | null>(null);
const processingId = ref<string | null>(null);

function toggleStatus(entity: LegalEntity) {
    processingId.value = entity.id;
    router.patch(
        statusRoute(entity.id).url,
        { active: entity.status !== 'active' },
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function makePrimary(entity: LegalEntity) {
    processingId.value = entity.id;
    router.put(
        primaryRoute(entity.id).url,
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

function restore(entity: LegalEntity) {
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
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Entidades legais"
                description="Entidades legais (CPF/CNPJ) da sua clínica"
            />
            <Link :href="create()">
                <Button>Nova entidade legal</Button>
            </Link>
        </div>

        <Card v-if="legalEntities.length === 0">
            <CardContent
                class="py-10 text-center text-sm text-muted-foreground"
            >
                Nenhuma entidade legal cadastrada ainda.
            </CardContent>
        </Card>

        <div v-else class="grid gap-3">
            <Card v-for="entity in legalEntities" :key="entity.id">
                <CardContent
                    class="flex flex-wrap items-center justify-between gap-3 py-4"
                >
                    <div>
                        <p class="font-medium">
                            {{ entity.legal_name }}
                            <Badge
                                v-if="entity.is_primary"
                                variant="secondary"
                                class="ml-2"
                                >Principal</Badge
                            >
                            <Badge
                                v-if="entity.deleted_at"
                                variant="destructive"
                                class="ml-2"
                                >Excluída</Badge
                            >
                            <Badge
                                v-else
                                :variant="
                                    entity.status === 'active'
                                        ? 'default'
                                        : 'destructive'
                                "
                                class="ml-2"
                            >
                                {{
                                    entity.status === 'active'
                                        ? 'Ativa'
                                        : 'Inativa'
                                }}
                            </Badge>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ entity.document }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <template v-if="entity.deleted_at">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="processingId === entity.id"
                                @click="restore(entity)"
                            >
                                Restaurar
                            </Button>
                        </template>
                        <template v-else>
                            <Link :href="edit(entity.id)">
                                <Button variant="outline" size="sm"
                                    >Editar</Button
                                >
                            </Link>
                            <Button
                                v-if="!entity.is_primary"
                                variant="outline"
                                size="sm"
                                :disabled="processingId === entity.id"
                                @click="toggleStatus(entity)"
                            >
                                {{
                                    entity.status === 'active'
                                        ? 'Inativar'
                                        : 'Ativar'
                                }}
                            </Button>
                            <Button
                                v-if="
                                    !entity.is_primary &&
                                    entity.status === 'active'
                                "
                                variant="outline"
                                size="sm"
                                :disabled="processingId === entity.id"
                                @click="makePrimary(entity)"
                            >
                                Definir como principal
                            </Button>
                            <Button
                                v-if="!entity.is_primary"
                                variant="destructive"
                                size="sm"
                                :disabled="processingId === entity.id"
                                @click="pendingDeletion = entity"
                            >
                                Excluir
                            </Button>
                        </template>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Dialog
            :open="pendingDeletion !== null"
            @update:open="(open) => !open && (pendingDeletion = null)"
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
                    <Button variant="destructive" @click="confirmDelete"
                        >Excluir</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

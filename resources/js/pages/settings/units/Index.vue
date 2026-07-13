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
import {
    create,
    destroy,
    edit,
    headquarters as headquartersRoute,
    restore as restoreRoute,
    status as statusRoute,
} from '@/routes/settings/units';
import type { Unit } from '@/types/organization';

defineProps<{
    units: Unit[];
}>();

const unitPendingDeletion = ref<Unit | null>(null);
const processingUnitId = ref<string | null>(null);

function toggleStatus(unit: Unit) {
    processingUnitId.value = unit.id;
    router.patch(
        statusRoute(unit.id).url,
        { active: unit.status !== 'active' },
        {
            preserveScroll: true,
            onFinish: () => (processingUnitId.value = null),
        },
    );
}

function makeHeadquarters(unit: Unit) {
    processingUnitId.value = unit.id;
    router.put(
        headquartersRoute(unit.id).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => (processingUnitId.value = null),
        },
    );
}

function confirmDelete() {
    if (!unitPendingDeletion.value) {
        return;
    }

    const id = unitPendingDeletion.value.id;
    processingUnitId.value = id;
    router.delete(destroy(id).url, {
        preserveScroll: true,
        onFinish: () => {
            processingUnitId.value = null;
            unitPendingDeletion.value = null;
        },
    });
}

function restore(unit: Unit) {
    processingUnitId.value = unit.id;
    router.post(
        restoreRoute(unit.id).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => (processingUnitId.value = null),
        },
    );
}
</script>

<template>
    <Head title="Unidades" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Unidades"
                description="Unidades da sua organização"
            />
            <Link :href="create()">
                <Button>Nova unidade</Button>
            </Link>
        </div>

        <Card v-if="units.length === 0">
            <CardContent
                class="py-10 text-center text-sm text-muted-foreground"
            >
                Nenhuma unidade cadastrada ainda.
            </CardContent>
        </Card>

        <div v-else class="grid gap-3">
            <Card v-for="unit in units" :key="unit.id">
                <CardContent
                    class="flex flex-wrap items-center justify-between gap-3 py-4"
                >
                    <div>
                        <p class="font-medium">
                            {{ unit.name }}
                            <Badge
                                v-if="unit.is_headquarters"
                                variant="secondary"
                                class="ml-2"
                                >Matriz</Badge
                            >
                            <Badge
                                v-if="unit.deleted_at"
                                variant="destructive"
                                class="ml-2"
                                >Excluída</Badge
                            >
                            <Badge
                                v-else
                                :variant="
                                    unit.status === 'active'
                                        ? 'default'
                                        : 'destructive'
                                "
                                class="ml-2"
                            >
                                {{
                                    unit.status === 'active'
                                        ? 'Ativa'
                                        : 'Inativa'
                                }}
                            </Badge>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ unit.code }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <template v-if="unit.deleted_at">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="processingUnitId === unit.id"
                                @click="restore(unit)"
                            >
                                Restaurar
                            </Button>
                        </template>
                        <template v-else>
                            <Link :href="edit(unit.id)">
                                <Button variant="outline" size="sm"
                                    >Editar</Button
                                >
                            </Link>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="processingUnitId === unit.id"
                                @click="toggleStatus(unit)"
                            >
                                {{
                                    unit.status === 'active'
                                        ? 'Inativar'
                                        : 'Ativar'
                                }}
                            </Button>
                            <Button
                                v-if="
                                    !unit.is_headquarters &&
                                    unit.status === 'active'
                                "
                                variant="outline"
                                size="sm"
                                :disabled="processingUnitId === unit.id"
                                @click="makeHeadquarters(unit)"
                            >
                                Definir como matriz
                            </Button>
                            <Button
                                v-if="!unit.is_headquarters"
                                variant="destructive"
                                size="sm"
                                :disabled="processingUnitId === unit.id"
                                @click="unitPendingDeletion = unit"
                            >
                                Excluir
                            </Button>
                        </template>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Dialog
            :open="unitPendingDeletion !== null"
            @update:open="(open) => !open && (unitPendingDeletion = null)"
        >
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Excluir unidade?</DialogTitle>
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

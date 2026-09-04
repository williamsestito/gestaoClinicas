<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import IconPicker from '@/components/IconPicker.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import SiteCollectionRowActions from '@/components/site/SiteCollectionRowActions.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import {
    destroy,
    reorder,
    store,
    toggle,
    update,
} from '@/routes/settings/site/benefits';
import type { SiteBenefit } from '@/types/site';

const props = defineProps<{
    benefits: SiteBenefit[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Presença digital' },
            { title: 'Site da clínica' },
            { title: 'Benefícios' },
        ],
    },
});

const sheetOpen = ref(false);
const sheetMode = ref<'create' | 'edit'>('create');
const editingItem = ref<SiteBenefit | null>(null);
const processingId = ref<number | null>(null);
const itemPendingDeletion = ref<SiteBenefit | null>(null);

const form = useForm({
    icon: '',
    title: '',
    description: '',
});

function openCreateSheet() {
    sheetMode.value = 'create';
    editingItem.value = null;
    form.reset();
    form.clearErrors();
    sheetOpen.value = true;
}

function openEditSheet(item: SiteBenefit) {
    sheetMode.value = 'edit';
    editingItem.value = item;
    form.icon = item.icon ?? '';
    form.title = item.title;
    form.description = item.description ?? '';
    form.clearErrors();
    sheetOpen.value = true;
}

function submit() {
    if (sheetMode.value === 'create') {
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => (sheetOpen.value = false),
        });
    } else if (editingItem.value) {
        form.put(update(editingItem.value.id).url, {
            preserveScroll: true,
            onSuccess: () => (sheetOpen.value = false),
        });
    }
}

function toggleActive(item: SiteBenefit) {
    processingId.value = item.id;
    router.patch(
        toggle(item.id).url,
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function moveUp(index: number) {
    if (index === 0) {
        return;
    }

    reorderItems(index, index - 1);
}

function moveDown(index: number) {
    if (index === props.benefits.length - 1) {
        return;
    }

    reorderItems(index, index + 1);
}

function reorderItems(from: number, to: number) {
    const ids = props.benefits.map((item) => item.id);
    [ids[from], ids[to]] = [ids[to], ids[from]];

    router.patch(reorder().url, { ids }, { preserveScroll: true });
}

function confirmDelete() {
    if (!itemPendingDeletion.value) {
        return;
    }

    const id = itemPendingDeletion.value.id;
    processingId.value = id;
    router.delete(destroy(id).url, {
        preserveScroll: true,
        onFinish: () => {
            processingId.value = null;
            itemPendingDeletion.value = null;
        },
    });
}
</script>

<template>
    <Head title="Benefícios" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Benefícios"
            description="Diferenciais exibidos na landing pública (atendimento humanizado, tecnologia, etc)."
        >
            <template #actions>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Novo benefício
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="benefits.length === 0"
            title="Nenhum benefício cadastrado ainda."
            description="Cadastre os diferenciais da clínica para exibir na página pública."
        >
            <template #action>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Cadastrar primeiro benefício
                </Button>
            </template>
        </EmptyState>

        <div v-else class="grid gap-3">
            <Card v-for="(item, index) in benefits" :key="item.id">
                <CardContent
                    class="flex items-center justify-between gap-4 py-4"
                >
                    <div>
                        <p class="font-medium">{{ item.title }}</p>
                        <p
                            v-if="item.description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ item.description }}
                        </p>
                        <Badge
                            :variant="item.is_active ? 'default' : 'secondary'"
                            class="mt-1"
                        >
                            {{ item.is_active ? 'Ativo' : 'Inativo' }}
                        </Badge>
                    </div>

                    <SiteCollectionRowActions
                        :label="item.title"
                        :is-active="item.is_active"
                        :can-move-up="index > 0"
                        :can-move-down="index < benefits.length - 1"
                        :disabled="processingId === item.id"
                        @edit="openEditSheet(item)"
                        @toggle-active="toggleActive(item)"
                        @move-up="moveUp(index)"
                        @move-down="moveDown(index)"
                        @delete="itemPendingDeletion = item"
                    />
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
                        {{
                            sheetMode === 'create'
                                ? 'Novo benefício'
                                : 'Editar benefício'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        Exibido na seção de diferenciais da landing pública.
                    </SheetDescription>
                </SheetHeader>

                <form
                    class="flex flex-col gap-6 px-4 pb-6"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-2">
                        <Label for="icon">Ícone (opcional)</Label>
                        <IconPicker id="icon" v-model="form.icon" />
                        <InputError :message="form.errors.icon" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="title">Título</Label>
                        <Input id="title" v-model="form.title" autofocus />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Descrição</Label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        Salvar
                    </Button>
                </form>
            </SheetContent>
        </Sheet>

        <Dialog
            :open="itemPendingDeletion !== null"
            @update:open="(open) => !open && (itemPendingDeletion = null)"
        >
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Excluir benefício?</DialogTitle>
                    <DialogDescription>
                        Este item deixará de aparecer na página pública.
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

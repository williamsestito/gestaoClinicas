<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import ImageUploadField from '@/components/ImageUploadField.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import SiteCollectionRowActions from '@/components/site/SiteCollectionRowActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { dashboard } from '@/routes';
import {
    destroy,
    reorder,
    store,
    toggle,
    update,
} from '@/routes/settings/site/partners';
import type { SitePartner } from '@/types/site';

const props = defineProps<{
    partners: SitePartner[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Presença digital' },
            { title: 'Site da clínica' },
            { title: 'Convênios e parceiros' },
        ],
    },
});

const sheetOpen = ref(false);
const sheetMode = ref<'create' | 'edit'>('create');
const editingItem = ref<SitePartner | null>(null);
const processingId = ref<number | null>(null);
const itemPendingDeletion = ref<SitePartner | null>(null);

const form = useForm({
    name: '',
    url: '',
    logo: null as File | null,
});

function openCreateSheet() {
    sheetMode.value = 'create';
    editingItem.value = null;
    form.reset();
    form.clearErrors();
    sheetOpen.value = true;
}

function openEditSheet(item: SitePartner) {
    sheetMode.value = 'edit';
    editingItem.value = item;
    form.name = item.name;
    form.url = item.url ?? '';
    form.logo = null;
    form.clearErrors();
    sheetOpen.value = true;
}

function submit() {
    const url =
        sheetMode.value === 'create'
            ? store().url
            : update(editingItem.value!.id).url;

    form.transform((data) => ({
        ...data,
        _method: sheetMode.value === 'create' ? 'post' : 'put',
    })).post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (sheetOpen.value = false),
    });
}

function toggleActive(item: SitePartner) {
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
    if (index === props.partners.length - 1) {
        return;
    }

    reorderItems(index, index + 1);
}

function reorderItems(from: number, to: number) {
    const ids = props.partners.map((item) => item.id);
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
    <Head title="Convênios e parceiros" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Convênios e parceiros"
            description="Logotipos exibidos numa faixa discreta na landing pública."
        >
            <template #actions>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Novo parceiro
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="partners.length === 0"
            title="Nenhum convênio ou parceiro cadastrado ainda."
            description="Enquanto não houver nenhum, a seção não aparece na landing pública."
        >
            <template #action>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Adicionar primeiro parceiro
                </Button>
            </template>
        </EmptyState>

        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="(item, index) in partners"
                :key="item.id"
                class="overflow-hidden rounded-md border"
            >
                <div
                    class="flex h-24 items-center justify-center bg-muted/40 p-4"
                >
                    <img
                        v-if="item.logo_url"
                        :src="item.logo_url"
                        :alt="item.name"
                        class="max-h-full max-w-full object-contain"
                    />
                    <span v-else class="text-sm text-muted-foreground">{{
                        item.name
                    }}</span>
                </div>
                <div class="flex items-center justify-between gap-2 p-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ item.name }}
                        </p>
                        <Badge :variant="item.is_active ? 'default' : 'secondary'">
                            {{ item.is_active ? 'Ativo' : 'Inativo' }}
                        </Badge>
                    </div>
                    <SiteCollectionRowActions
                        :label="item.name"
                        :is-active="item.is_active"
                        :can-move-up="index > 0"
                        :can-move-down="index < partners.length - 1"
                        :disabled="processingId === item.id"
                        @edit="openEditSheet(item)"
                        @toggle-active="toggleActive(item)"
                        @move-up="moveUp(index)"
                        @move-down="moveDown(index)"
                        @delete="itemPendingDeletion = item"
                    />
                </div>
            </div>
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
                                ? 'Novo parceiro'
                                : 'Editar parceiro'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        Exibido na faixa de convênios e parceiros da landing
                        pública.
                    </SheetDescription>
                </SheetHeader>

                <form
                    class="flex flex-col gap-6 px-4 pb-6"
                    @submit.prevent="submit"
                >
                    <ImageUploadField
                        id="logo"
                        v-model="form.logo"
                        label="Logotipo"
                        :current-url="editingItem?.logo_url"
                    />
                    <InputError :message="form.errors.logo" />

                    <div class="grid gap-2">
                        <Label for="name">Nome</Label>
                        <Input id="name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="url">Site (opcional)</Label>
                        <Input
                            id="url"
                            v-model="form.url"
                            type="url"
                            placeholder="https://..."
                        />
                        <InputError :message="form.errors.url" />
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
                    <DialogTitle>Remover parceiro?</DialogTitle>
                    <DialogDescription>
                        Este logotipo deixará de aparecer na landing pública.
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

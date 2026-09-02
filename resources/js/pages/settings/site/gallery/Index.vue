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
import { Checkbox } from '@/components/ui/checkbox';
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
} from '@/routes/settings/site/gallery';
import type { SiteGalleryItem } from '@/types/site';

const props = defineProps<{
    items: SiteGalleryItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Presença digital' },
            { title: 'Site da clínica' },
            { title: 'Galeria' },
        ],
    },
});

const sheetOpen = ref(false);
const sheetMode = ref<'create' | 'edit'>('create');
const editingItem = ref<SiteGalleryItem | null>(null);
const processingId = ref<number | null>(null);
const itemPendingDeletion = ref<SiteGalleryItem | null>(null);

const form = useForm({
    caption: '',
    alt_text: '',
    category: '',
    is_cover: false,
    image: null as File | null,
});

function openCreateSheet() {
    sheetMode.value = 'create';
    editingItem.value = null;
    form.reset();
    form.clearErrors();
    sheetOpen.value = true;
}

function openEditSheet(item: SiteGalleryItem) {
    sheetMode.value = 'edit';
    editingItem.value = item;
    form.caption = item.caption ?? '';
    form.alt_text = item.alt_text ?? '';
    form.category = item.category ?? '';
    form.is_cover = item.is_cover;
    form.image = null;
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

function toggleActive(item: SiteGalleryItem) {
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
    if (index === props.items.length - 1) {
        return;
    }

    reorderItems(index, index + 1);
}

function reorderItems(from: number, to: number) {
    const ids = props.items.map((item) => item.id);
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
    <Head title="Galeria" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Galeria"
            description="Imagens institucionais exibidas na landing pública (uma por vez)."
        >
            <template #actions>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Nova imagem
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="items.length === 0"
            title="Nenhuma imagem na galeria ainda."
            description="Adicione fotos da estrutura, equipe ou equipamentos."
        >
            <template #action>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Adicionar primeira imagem
                </Button>
            </template>
        </EmptyState>

        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="(item, index) in items"
                :key="item.id"
                class="overflow-hidden rounded-md border"
            >
                <img
                    :src="item.image_url"
                    :alt="item.alt_text ?? item.caption ?? ''"
                    class="h-40 w-full object-cover"
                />
                <div class="flex items-center justify-between gap-2 p-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ item.caption || 'Sem legenda' }}
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <Badge
                                :variant="
                                    item.is_active ? 'default' : 'secondary'
                                "
                            >
                                {{ item.is_active ? 'Ativa' : 'Inativa' }}
                            </Badge>
                            <Badge v-if="item.is_cover" variant="secondary"
                                >Capa</Badge
                            >
                        </div>
                    </div>
                    <SiteCollectionRowActions
                        :label="item.caption || 'imagem'"
                        :is-active="item.is_active"
                        :can-move-up="index > 0"
                        :can-move-down="index < items.length - 1"
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
                                ? 'Nova imagem'
                                : 'Editar imagem'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        Exibida na galeria da landing pública.
                    </SheetDescription>
                </SheetHeader>

                <form
                    class="flex flex-col gap-6 px-4 pb-6"
                    @submit.prevent="submit"
                >
                    <ImageUploadField
                        id="image"
                        v-model="form.image"
                        label="Imagem"
                        :current-url="editingItem?.image_url"
                    />
                    <InputError :message="form.errors.image" />

                    <div class="grid gap-2">
                        <Label for="caption">Legenda</Label>
                        <Input id="caption" v-model="form.caption" />
                        <InputError :message="form.errors.caption" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="alt_text">Texto alternativo</Label>
                        <Input id="alt_text" v-model="form.alt_text" />
                        <InputError :message="form.errors.alt_text" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="category">Categoria</Label>
                        <Input
                            id="category"
                            v-model="form.category"
                            placeholder="Estrutura, Equipe..."
                        />
                        <InputError :message="form.errors.category" />
                    </div>

                    <Label class="flex items-center gap-2 font-normal">
                        <Checkbox v-model:model-value="form.is_cover" />
                        Usar como imagem de capa
                    </Label>

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
                    <DialogTitle>Remover imagem?</DialogTitle>
                    <DialogDescription>
                        Esta imagem deixará de aparecer na galeria pública.
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

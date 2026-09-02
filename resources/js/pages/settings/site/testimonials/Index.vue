<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Star } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import ImageUploadField from '@/components/ImageUploadField.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import SiteCollectionRowActions from '@/components/site/SiteCollectionRowActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
} from '@/routes/settings/site/testimonials';
import type { SiteServiceOption, SiteTestimonial } from '@/types/site';

const props = defineProps<{
    testimonials: SiteTestimonial[];
    services: SiteServiceOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Presença digital' },
            { title: 'Site da clínica' },
            { title: 'Depoimentos' },
        ],
    },
});

const sheetOpen = ref(false);
const sheetMode = ref<'create' | 'edit'>('create');
const editingItem = ref<SiteTestimonial | null>(null);
const processingId = ref<number | null>(null);
const itemPendingDeletion = ref<SiteTestimonial | null>(null);

const form = useForm({
    author_name: '',
    rating: undefined as number | undefined,
    content: '',
    related_service_id: null as number | null,
    is_featured: false,
    photo: null as File | null,
});

function openCreateSheet() {
    sheetMode.value = 'create';
    editingItem.value = null;
    form.reset();
    form.clearErrors();
    sheetOpen.value = true;
}

function openEditSheet(item: SiteTestimonial) {
    sheetMode.value = 'edit';
    editingItem.value = item;
    form.author_name = item.author_name;
    form.rating = item.rating ?? undefined;
    form.content = item.content;
    form.related_service_id = item.related_service_id;
    form.is_featured = item.is_featured;
    form.photo = null;
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
        rating: data.rating ?? null,
        _method: sheetMode.value === 'create' ? 'post' : 'put',
    })).post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (sheetOpen.value = false),
    });
}

function toggleActive(item: SiteTestimonial) {
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
    if (index === props.testimonials.length - 1) {
        return;
    }

    reorderItems(index, index + 1);
}

function reorderItems(from: number, to: number) {
    const ids = props.testimonials.map((item) => item.id);
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
    <Head title="Depoimentos" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Depoimentos"
            description="Prova social exibida na landing pública. A foto é sempre opcional."
        >
            <template #actions>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Novo depoimento
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="testimonials.length === 0"
            title="Nenhum depoimento cadastrado ainda."
        >
            <template #action>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Cadastrar primeiro depoimento
                </Button>
            </template>
        </EmptyState>

        <div v-else class="grid gap-3">
            <Card v-for="(item, index) in testimonials" :key="item.id">
                <CardContent
                    class="flex items-center justify-between gap-4 py-4"
                >
                    <div class="flex items-center gap-3">
                        <img
                            v-if="item.author_photo_url"
                            :src="item.author_photo_url"
                            :alt="item.author_name"
                            class="size-12 rounded-full border object-cover"
                        />
                        <div>
                            <div class="flex items-center gap-1">
                                <p class="font-medium">
                                    {{ item.author_name }}
                                </p>
                                <span
                                    v-if="item.rating"
                                    class="flex items-center gap-0.5 text-amber-500"
                                >
                                    <Star class="size-3.5 fill-current" />
                                    {{ item.rating }}
                                </span>
                            </div>
                            <p
                                class="line-clamp-1 text-sm text-muted-foreground"
                            >
                                {{ item.content }}
                            </p>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <Badge
                                    :variant="
                                        item.is_active ? 'default' : 'secondary'
                                    "
                                >
                                    {{ item.is_active ? 'Ativo' : 'Inativo' }}
                                </Badge>
                                <Badge
                                    v-if="item.is_featured"
                                    variant="secondary"
                                    >Destaque</Badge
                                >
                            </div>
                        </div>
                    </div>

                    <SiteCollectionRowActions
                        :label="item.author_name"
                        :is-active="item.is_active"
                        :can-move-up="index > 0"
                        :can-move-down="index < testimonials.length - 1"
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
                                ? 'Novo depoimento'
                                : 'Editar depoimento'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        Exibido na seção de depoimentos da landing pública.
                    </SheetDescription>
                </SheetHeader>

                <form
                    class="flex flex-col gap-6 px-4 pb-6"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-2">
                        <Label for="author_name">Nome do cliente</Label>
                        <Input
                            id="author_name"
                            v-model="form.author_name"
                            autofocus
                        />
                        <InputError :message="form.errors.author_name" />
                    </div>

                    <ImageUploadField
                        id="photo"
                        v-model="form.photo"
                        label="Foto (opcional)"
                        :current-url="editingItem?.author_photo_url"
                    />

                    <div class="grid gap-2">
                        <Label for="content">Depoimento</Label>
                        <Textarea
                            id="content"
                            v-model="form.content"
                            rows="4"
                        />
                        <InputError :message="form.errors.content" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="rating">Avaliação (1-5)</Label>
                            <Input
                                id="rating"
                                v-model.number="form.rating"
                                type="number"
                                min="1"
                                max="5"
                            />
                            <InputError :message="form.errors.rating" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="related_service_id"
                                >Serviço relacionado</Label
                            >
                            <Select
                                :model-value="
                                    form.related_service_id?.toString() ??
                                    undefined
                                "
                                @update:model-value="
                                    (v) =>
                                        (form.related_service_id = v
                                            ? Number(v)
                                            : null)
                                "
                            >
                                <SelectTrigger
                                    id="related_service_id"
                                    class="w-full"
                                >
                                    <SelectValue placeholder="Nenhum" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="service in services"
                                        :key="service.id"
                                        :value="service.id.toString()"
                                    >
                                        {{ service.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.related_service_id"
                            />
                        </div>
                    </div>

                    <Label class="flex items-center gap-2 font-normal">
                        <Checkbox v-model:model-value="form.is_featured" />
                        Destacar este depoimento
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
                    <DialogTitle>Excluir depoimento?</DialogTitle>
                    <DialogDescription>
                        Este depoimento deixará de aparecer na página pública.
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

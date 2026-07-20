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
} from '@/routes/settings/site/services';
import type { SiteService } from '@/types/site';

const props = defineProps<{
    services: SiteService[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Presença digital' },
            { title: 'Site da clínica' },
            { title: 'Serviços' },
        ],
    },
});

const sheetOpen = ref(false);
const sheetMode = ref<'create' | 'edit'>('create');
const editingItem = ref<SiteService | null>(null);
const processingId = ref<number | null>(null);
const itemPendingDeletion = ref<SiteService | null>(null);

const form = useForm({
    name: '',
    short_description: '',
    description: '',
    category: '',
    duration_minutes: undefined as number | undefined,
    starting_price: undefined as number | undefined,
    cta_text: '',
    is_featured: false,
    image: null as File | null,
});

function openCreateSheet() {
    sheetMode.value = 'create';
    editingItem.value = null;
    form.reset();
    form.clearErrors();
    sheetOpen.value = true;
}

function openEditSheet(item: SiteService) {
    sheetMode.value = 'edit';
    editingItem.value = item;
    form.name = item.name;
    form.short_description = item.short_description ?? '';
    form.description = item.description ?? '';
    form.category = item.category ?? '';
    form.duration_minutes = item.duration_minutes ?? undefined;
    form.starting_price = item.starting_price_cents
        ? item.starting_price_cents / 100
        : undefined;
    form.cta_text = item.cta_text ?? '';
    form.is_featured = item.is_featured;
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
        duration_minutes: data.duration_minutes ?? null,
        starting_price: data.starting_price ?? null,
        _method: sheetMode.value === 'create' ? 'post' : 'put',
    })).post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (sheetOpen.value = false),
    });
}

function toggleActive(item: SiteService) {
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
    if (index === props.services.length - 1) {
        return;
    }

    reorderItems(index, index + 1);
}

function reorderItems(from: number, to: number) {
    const ids = props.services.map((item) => item.id);
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
    <Head title="Serviços" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Serviços"
            description="Tratamentos e procedimentos exibidos na landing pública."
        >
            <template #actions>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Novo serviço
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="services.length === 0"
            title="Nenhum serviço cadastrado ainda."
            description="Cadastre os serviços/tratamentos oferecidos pela clínica."
        >
            <template #action>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Cadastrar primeiro serviço
                </Button>
            </template>
        </EmptyState>

        <div v-else class="grid gap-3">
            <Card v-for="(item, index) in services" :key="item.id">
                <CardContent
                    class="flex items-center justify-between gap-4 py-4"
                >
                    <div class="flex items-center gap-3">
                        <img
                            v-if="item.image_url"
                            :src="item.image_url"
                            :alt="item.name"
                            class="size-12 rounded-md border object-cover"
                        />
                        <div>
                            <p class="font-medium">{{ item.name }}</p>
                            <p
                                v-if="item.short_description"
                                class="text-sm text-muted-foreground"
                            >
                                {{ item.short_description }}
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
                                >
                                    Destaque
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <SiteCollectionRowActions
                        :label="item.name"
                        :is-active="item.is_active"
                        :can-move-up="index > 0"
                        :can-move-down="index < services.length - 1"
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
                                ? 'Novo serviço'
                                : 'Editar serviço'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        Exibido na seção de serviços da landing pública.
                    </SheetDescription>
                </SheetHeader>

                <form
                    class="flex flex-col gap-6 px-4 pb-6"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-2">
                        <Label for="name">Nome</Label>
                        <Input id="name" v-model="form.name" autofocus />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="short_description">Descrição curta</Label>
                        <Input
                            id="short_description"
                            v-model="form.short_description"
                        />
                        <InputError :message="form.errors.short_description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Descrição completa</Label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <ImageUploadField
                        id="image"
                        v-model="form.image"
                        label="Imagem"
                        :current-url="editingItem?.image_url"
                    />

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="category">Categoria</Label>
                            <Input id="category" v-model="form.category" />
                            <InputError :message="form.errors.category" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="duration_minutes">Duração (min)</Label>
                            <Input
                                id="duration_minutes"
                                v-model.number="form.duration_minutes"
                                type="number"
                                min="1"
                            />
                            <InputError
                                :message="form.errors.duration_minutes"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="starting_price"
                                >Preço a partir de (R$)</Label
                            >
                            <Input
                                id="starting_price"
                                v-model.number="form.starting_price"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                            <InputError :message="form.errors.starting_price" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="cta_text">Texto do botão</Label>
                            <Input
                                id="cta_text"
                                v-model="form.cta_text"
                                placeholder="Agendar avaliação"
                            />
                            <InputError :message="form.errors.cta_text" />
                        </div>
                    </div>

                    <Label class="flex items-center gap-2 font-normal">
                        <Checkbox v-model:model-value="form.is_featured" />
                        Destacar este serviço
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
                    <DialogTitle>Excluir serviço?</DialogTitle>
                    <DialogDescription>
                        Este serviço deixará de aparecer na página pública.
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

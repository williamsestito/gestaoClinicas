<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
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
} from '@/routes/settings/site/faq';
import type { SiteFaq } from '@/types/site';

const props = defineProps<{
    faqs: SiteFaq[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Presença digital' },
            { title: 'Site da clínica' },
            { title: 'FAQ' },
        ],
    },
});

const sheetOpen = ref(false);
const sheetMode = ref<'create' | 'edit'>('create');
const editingItem = ref<SiteFaq | null>(null);
const processingId = ref<number | null>(null);
const itemPendingDeletion = ref<SiteFaq | null>(null);

const form = useForm({
    question: '',
    answer: '',
    category: '',
});

function openCreateSheet() {
    sheetMode.value = 'create';
    editingItem.value = null;
    form.reset();
    form.clearErrors();
    sheetOpen.value = true;
}

function openEditSheet(item: SiteFaq) {
    sheetMode.value = 'edit';
    editingItem.value = item;
    form.question = item.question;
    form.answer = item.answer;
    form.category = item.category ?? '';
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

function toggleActive(item: SiteFaq) {
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
    if (index === props.faqs.length - 1) {
        return;
    }

    reorderItems(index, index + 1);
}

function reorderItems(from: number, to: number) {
    const ids = props.faqs.map((item) => item.id);
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
    <Head title="Perguntas frequentes" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Perguntas frequentes"
            description="FAQ exibido na landing pública."
        >
            <template #actions>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Nova pergunta
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="faqs.length === 0"
            title="Nenhuma pergunta cadastrada ainda."
        >
            <template #action>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Cadastrar primeira pergunta
                </Button>
            </template>
        </EmptyState>

        <div v-else class="grid gap-3">
            <Card v-for="(item, index) in faqs" :key="item.id">
                <CardContent
                    class="flex items-center justify-between gap-4 py-4"
                >
                    <div>
                        <p class="font-medium">{{ item.question }}</p>
                        <p class="text-muted-foreground line-clamp-1 text-sm">
                            {{ item.answer }}
                        </p>
                        <Badge
                            :variant="item.is_active ? 'default' : 'secondary'"
                            class="mt-1"
                        >
                            {{ item.is_active ? 'Ativa' : 'Inativa' }}
                        </Badge>
                    </div>

                    <SiteCollectionRowActions
                        :label="item.question"
                        :is-active="item.is_active"
                        :can-move-up="index > 0"
                        :can-move-down="index < faqs.length - 1"
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
                                ? 'Nova pergunta'
                                : 'Editar pergunta'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        Exibida na seção de perguntas frequentes da landing
                        pública.
                    </SheetDescription>
                </SheetHeader>

                <form
                    class="flex flex-col gap-6 px-4 pb-6"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-2">
                        <Label for="question">Pergunta</Label>
                        <Input
                            id="question"
                            v-model="form.question"
                            autofocus
                        />
                        <InputError :message="form.errors.question" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="answer">Resposta</Label>
                        <Textarea id="answer" v-model="form.answer" rows="4" />
                        <InputError :message="form.errors.answer" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="category">Categoria</Label>
                        <Input id="category" v-model="form.category" />
                        <InputError :message="form.errors.category" />
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
                    <DialogTitle>Excluir pergunta?</DialogTitle>
                    <DialogDescription>
                        Esta pergunta deixará de aparecer na página pública.
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

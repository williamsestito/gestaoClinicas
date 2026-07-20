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
} from '@/routes/settings/site/professionals';
import type { SiteProfessional } from '@/types/site';

const props = defineProps<{
    professionals: SiteProfessional[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Presença digital' },
            { title: 'Site da clínica' },
            { title: 'Equipe' },
        ],
    },
});

const sheetOpen = ref(false);
const sheetMode = ref<'create' | 'edit'>('create');
const editingItem = ref<SiteProfessional | null>(null);
const processingId = ref<number | null>(null);
const itemPendingDeletion = ref<SiteProfessional | null>(null);

const form = useForm({
    name: '',
    role_title: '',
    specialty: '',
    professional_register: '',
    bio: '',
    facebook_url: '',
    instagram_url: '',
    linkedin_url: '',
    photo: null as File | null,
});

function openCreateSheet() {
    sheetMode.value = 'create';
    editingItem.value = null;
    form.reset();
    form.clearErrors();
    sheetOpen.value = true;
}

function openEditSheet(item: SiteProfessional) {
    sheetMode.value = 'edit';
    editingItem.value = item;
    form.name = item.name;
    form.role_title = item.role_title ?? '';
    form.specialty = item.specialty ?? '';
    form.professional_register = item.professional_register ?? '';
    form.bio = item.bio ?? '';
    form.facebook_url = item.facebook_url ?? '';
    form.instagram_url = item.instagram_url ?? '';
    form.linkedin_url = item.linkedin_url ?? '';
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
        _method: sheetMode.value === 'create' ? 'post' : 'put',
    })).post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (sheetOpen.value = false),
    });
}

function toggleActive(item: SiteProfessional) {
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
    if (index === props.professionals.length - 1) {
        return;
    }

    reorderItems(index, index + 1);
}

function reorderItems(from: number, to: number) {
    const ids = props.professionals.map((item) => item.id);
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
    <Head title="Equipe" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Equipe"
            description="Profissionais exibidos na landing pública."
        >
            <template #actions>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Novo profissional
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="professionals.length === 0"
            title="Nenhum profissional cadastrado ainda."
            description="Cadastre a equipe da clínica para exibir na página pública."
        >
            <template #action>
                <Button @click="openCreateSheet">
                    <Plus class="size-4" />
                    Cadastrar primeiro profissional
                </Button>
            </template>
        </EmptyState>

        <div v-else class="grid gap-3">
            <Card v-for="(item, index) in professionals" :key="item.id">
                <CardContent
                    class="flex items-center justify-between gap-4 py-4"
                >
                    <div class="flex items-center gap-3">
                        <img
                            v-if="item.photo_url"
                            :src="item.photo_url"
                            :alt="item.name"
                            class="size-12 rounded-full border object-cover"
                        />
                        <div>
                            <p class="font-medium">{{ item.name }}</p>
                            <p
                                v-if="item.role_title"
                                class="text-sm text-muted-foreground"
                            >
                                {{ item.role_title }}
                            </p>
                            <Badge
                                :variant="
                                    item.is_active ? 'default' : 'secondary'
                                "
                                class="mt-1"
                            >
                                {{ item.is_active ? 'Ativo' : 'Inativo' }}
                            </Badge>
                        </div>
                    </div>

                    <SiteCollectionRowActions
                        :label="item.name"
                        :is-active="item.is_active"
                        :can-move-up="index > 0"
                        :can-move-down="index < professionals.length - 1"
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
                                ? 'Novo profissional'
                                : 'Editar profissional'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        Exibido na seção de equipe da landing pública.
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

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="role_title">Cargo</Label>
                            <Input
                                id="role_title"
                                v-model="form.role_title"
                                placeholder="Dermatologista"
                            />
                            <InputError :message="form.errors.role_title" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="specialty">Especialidade</Label>
                            <Input id="specialty" v-model="form.specialty" />
                            <InputError :message="form.errors.specialty" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="professional_register"
                            >Registro profissional</Label
                        >
                        <Input
                            id="professional_register"
                            v-model="form.professional_register"
                            placeholder="CRM/SC 12345"
                        />
                        <InputError
                            :message="form.errors.professional_register"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="bio">Resumo</Label>
                        <Textarea id="bio" v-model="form.bio" rows="4" />
                        <InputError :message="form.errors.bio" />
                    </div>

                    <ImageUploadField
                        id="photo"
                        v-model="form.photo"
                        label="Foto"
                        :current-url="editingItem?.photo_url"
                    />

                    <div class="grid gap-4">
                        <p class="text-sm font-medium">Redes sociais</p>
                        <div class="grid gap-2">
                            <Label for="facebook_url">Facebook</Label>
                            <Input
                                id="facebook_url"
                                v-model="form.facebook_url"
                                type="url"
                            />
                            <InputError :message="form.errors.facebook_url" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="instagram_url">Instagram</Label>
                            <Input
                                id="instagram_url"
                                v-model="form.instagram_url"
                                type="url"
                            />
                            <InputError :message="form.errors.instagram_url" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="linkedin_url">LinkedIn</Label>
                            <Input
                                id="linkedin_url"
                                v-model="form.linkedin_url"
                                type="url"
                            />
                            <InputError :message="form.errors.linkedin_url" />
                        </div>
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
                    <DialogTitle>Excluir profissional?</DialogTitle>
                    <DialogDescription>
                        Este profissional deixará de aparecer na página pública.
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

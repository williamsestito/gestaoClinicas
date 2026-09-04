<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { ref } from 'vue';
import ImageUploadField from '@/components/ImageUploadField.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import { publish, unpublish, update } from '@/routes/settings/site';
import { destroy as destroyFavicon } from '@/routes/settings/site/favicon';
import { destroy as destroyHeroImage } from '@/routes/settings/site/hero-image';
import { destroy as destroyHeroImageMobile } from '@/routes/settings/site/hero-image-mobile';
import { destroy as destroyLogo } from '@/routes/settings/site/logo';

type SiteContent = {
    title: string;
    description: string | null;
    hero_image_url: string | null;
    hero_image_mobile_url: string | null;
    logo_url: string | null;
    favicon_url: string | null;
    primary_color: string | null;
    secondary_color: string | null;
    cta_text: string | null;
    cta_url: string | null;
    cta_secondary_text: string | null;
    cta_secondary_url: string | null;
    about_text: string | null;
    mission_text: string | null;
    vision_text: string | null;
    facebook_url: string | null;
    instagram_url: string | null;
    linkedin_url: string | null;
    footer_text: string | null;
    is_published: boolean;
} | null;

type Contact = {
    phone: string | null;
    whatsapp: string | null;
    email: string | null;
    address: {
        street: string;
        number: string;
        city: string;
        state: string;
    } | null;
} | null;

const props = defineProps<{
    site: SiteContent;
    contact: Contact;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Presença digital' },
            { title: 'Site da clínica' },
        ],
    },
});

const form = useForm({
    title: props.site?.title ?? '',
    description: props.site?.description ?? '',
    primary_color: props.site?.primary_color ?? '',
    secondary_color: props.site?.secondary_color ?? '',
    cta_text: props.site?.cta_text ?? '',
    cta_url: props.site?.cta_url ?? '',
    cta_secondary_text: props.site?.cta_secondary_text ?? '',
    cta_secondary_url: props.site?.cta_secondary_url ?? '',
    about_text: props.site?.about_text ?? '',
    mission_text: props.site?.mission_text ?? '',
    vision_text: props.site?.vision_text ?? '',
    facebook_url: props.site?.facebook_url ?? '',
    instagram_url: props.site?.instagram_url ?? '',
    linkedin_url: props.site?.linkedin_url ?? '',
    footer_text: props.site?.footer_text ?? '',
    hero_image: null as File | null,
    hero_image_mobile: null as File | null,
    logo: null as File | null,
    favicon: null as File | null,
});

function submit() {
    form.transform((data) => ({ ...data, _method: 'put' })).post(update().url, {
        forceFormData: true,
        preserveScroll: true,
    });
}

function togglePublish() {
    const action = props.site?.is_published ? unpublish : publish;
    router.patch(action().url, {}, { preserveScroll: true });
}

type RemovableAsset = 'hero_image' | 'hero_image_mobile' | 'logo' | 'favicon';

const ASSET_REMOVAL_CONFIG: Record<
    RemovableAsset,
    { destroyUrl: () => { url: string }; title: string; description: string }
> = {
    hero_image: {
        destroyUrl: destroyHeroImage,
        title: 'Remover banner desktop?',
        description:
            'O banner desktop deixará de aparecer na página pública até que um novo seja enviado.',
    },
    hero_image_mobile: {
        destroyUrl: destroyHeroImageMobile,
        title: 'Remover banner mobile?',
        description:
            'A versão mobile deixará de existir — em telas pequenas, a página voltará a usar o banner desktop até que um novo banner mobile seja enviado.',
    },
    logo: {
        destroyUrl: destroyLogo,
        title: 'Remover logotipo?',
        description:
            'O logotipo deixará de aparecer até que um novo seja enviado.',
    },
    favicon: {
        destroyUrl: destroyFavicon,
        title: 'Remover favicon?',
        description:
            'O favicon deixará de aparecer até que um novo seja enviado.',
    },
};

const assetPendingRemoval = ref<RemovableAsset | null>(null);
const removingAsset = ref(false);

function confirmAssetRemoval() {
    const asset = assetPendingRemoval.value;

    if (!asset) {
        return;
    }

    removingAsset.value = true;
    router.delete(ASSET_REMOVAL_CONFIG[asset].destroyUrl().url, {
        preserveScroll: true,
        onFinish: () => {
            removingAsset.value = false;
            assetPendingRemoval.value = null;
        },
    });
}
</script>

<template>
    <Head title="Site da clínica" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Site da clínica"
            description="Gerencie o conteúdo exibido na página pública."
        >
            <template #actions>
                <a href="/" target="_blank" rel="noopener noreferrer">
                    <Button variant="secondary">
                        <ExternalLink class="size-4" />
                        Visualizar site
                    </Button>
                </a>
                <Button v-if="site" @click="togglePublish">
                    {{ site.is_published ? 'Despublicar' : 'Publicar' }}
                </Button>
            </template>
        </PageHeader>

        <div class="flex items-center gap-2">
            <span class="text-muted-foreground text-sm">Status:</span>
            <Badge :variant="site?.is_published ? 'default' : 'secondary'">
                {{ site?.is_published ? 'Publicado' : 'Não publicado' }}
            </Badge>
        </div>

        <div v-if="contact" class="rounded-md border p-4 text-sm">
            <p class="font-medium">Contato exibido publicamente</p>
            <p class="text-muted-foreground">
                Vem da unidade matriz — para alterar, edite em
                <span class="text-foreground font-medium">Unidades</span>.
            </p>
            <ul class="text-muted-foreground mt-2 space-y-1">
                <li v-if="contact.phone">Telefone: {{ contact.phone }}</li>
                <li v-if="contact.whatsapp">
                    WhatsApp: {{ contact.whatsapp }}
                </li>
                <li v-if="contact.email">E-mail: {{ contact.email }}</li>
                <li v-if="contact.address">
                    Endereço: {{ contact.address.street }},
                    {{ contact.address.number }} — {{ contact.address.city }}/{{
                        contact.address.state
                    }}
                </li>
            </ul>
        </div>

        <form class="max-w-2xl space-y-6" @submit.prevent="submit">
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

            <ImageUploadField
                id="hero_image"
                v-model="form.hero_image"
                label="Banner desktop"
                :current-url="site?.hero_image_url"
                helperText="Exibido no topo da página pública em telas largas, ocupando toda a área disponível. Formato recomendado: WebP ou JPG. Proporção recomendada: 16:9 (ex.: 1920 × 1080 px). Formatos aceitos: JPEG, PNG ou WebP. Tamanho máximo: 5 MB."
            />
            <InputError :message="form.errors.hero_image" />
            <Button
                v-if="site?.hero_image_url && !form.hero_image"
                type="button"
                variant="ghost"
                size="sm"
                class="text-destructive hover:text-destructive -mt-4 w-fit"
                @click="assetPendingRemoval = 'hero_image'"
            >
                Remover banner desktop atual
            </Button>

            <ImageUploadField
                id="hero_image_mobile"
                v-model="form.hero_image_mobile"
                label="Banner mobile (opcional)"
                :current-url="site?.hero_image_mobile_url"
                helperText="Exibido no lugar do banner desktop quando a página é aberta em um celular. Se não for enviado, o banner desktop também é usado no mobile. Proporção recomendada: 1:1 ou 4:5 (ex.: 1080 × 1350 px). Formatos aceitos: JPEG, PNG ou WebP. Tamanho máximo: 5 MB."
            />
            <InputError :message="form.errors.hero_image_mobile" />
            <Button
                v-if="site?.hero_image_mobile_url && !form.hero_image_mobile"
                type="button"
                variant="ghost"
                size="sm"
                class="text-destructive hover:text-destructive -mt-4 w-fit"
                @click="assetPendingRemoval = 'hero_image_mobile'"
            >
                Remover banner mobile atual
            </Button>

            <ImageUploadField
                id="logo"
                v-model="form.logo"
                label="Logotipo"
                :current-url="site?.logo_url"
                helperText="Formatos aceitos: JPEG, PNG ou WebP. Tamanho máximo: 1 MB."
            />
            <InputError :message="form.errors.logo" />
            <Button
                v-if="site?.logo_url && !form.logo"
                type="button"
                variant="ghost"
                size="sm"
                class="text-destructive hover:text-destructive -mt-4 w-fit"
                @click="assetPendingRemoval = 'logo'"
            >
                Remover logotipo atual
            </Button>

            <ImageUploadField
                id="favicon"
                v-model="form.favicon"
                label="Favicon"
                :current-url="site?.favicon_url"
                helperText="Qualquer imagem comum serve — o sistema recorta ao centro, redimensiona e gera os tamanhos necessários automaticamente. Formatos aceitos: JPEG, PNG ou WebP. Tamanho máximo: 256 KB."
            />
            <InputError :message="form.errors.favicon" />
            <Button
                v-if="site?.favicon_url && !form.favicon"
                type="button"
                variant="ghost"
                size="sm"
                class="text-destructive hover:text-destructive -mt-4 w-fit"
                @click="assetPendingRemoval = 'favicon'"
            >
                Remover favicon atual
            </Button>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="primary_color">Cor primária</Label>
                    <Input
                        id="primary_color"
                        v-model="form.primary_color"
                        type="color"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="secondary_color">Cor secundária</Label>
                    <Input
                        id="secondary_color"
                        v-model="form.secondary_color"
                        type="color"
                    />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="cta_text">Texto do botão principal</Label>
                    <Input id="cta_text" v-model="form.cta_text" />
                    <InputError :message="form.errors.cta_text" />
                </div>
                <div class="grid gap-2">
                    <Label for="cta_url">Destino do botão</Label>
                    <Input id="cta_url" v-model="form.cta_url" type="url" />
                    <InputError :message="form.errors.cta_url" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="cta_secondary_text"
                        >Texto do botão secundário</Label
                    >
                    <Input
                        id="cta_secondary_text"
                        v-model="form.cta_secondary_text"
                    />
                    <InputError :message="form.errors.cta_secondary_text" />
                </div>
                <div class="grid gap-2">
                    <Label for="cta_secondary_url"
                        >Destino do botão secundário</Label
                    >
                    <Input
                        id="cta_secondary_url"
                        v-model="form.cta_secondary_url"
                        type="url"
                    />
                    <InputError :message="form.errors.cta_secondary_url" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="about_text">Sobre a clínica</Label>
                <Textarea id="about_text" v-model="form.about_text" rows="4" />
                <InputError :message="form.errors.about_text" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="mission_text">Missão (opcional)</Label>
                    <Textarea
                        id="mission_text"
                        v-model="form.mission_text"
                        rows="3"
                    />
                    <InputError :message="form.errors.mission_text" />
                </div>
                <div class="grid gap-2">
                    <Label for="vision_text">Visão (opcional)</Label>
                    <Textarea
                        id="vision_text"
                        v-model="form.vision_text"
                        rows="3"
                    />
                    <InputError :message="form.errors.vision_text" />
                </div>
            </div>

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

            <div class="grid gap-2">
                <Label for="footer_text">Texto do rodapé</Label>
                <Input id="footer_text" v-model="form.footer_text" />
                <InputError :message="form.errors.footer_text" />
            </div>

            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Salvar
            </Button>
        </form>

        <Dialog
            :open="assetPendingRemoval !== null"
            @update:open="(open) => !open && (assetPendingRemoval = null)"
        >
            <DialogContent v-if="assetPendingRemoval">
                <DialogHeader class="space-y-3">
                    <DialogTitle>{{
                        ASSET_REMOVAL_CONFIG[assetPendingRemoval].title
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            ASSET_REMOVAL_CONFIG[assetPendingRemoval]
                                .description
                        }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancelar</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        :disabled="removingAsset"
                        @click="confirmAssetRemoval"
                    >
                        <Spinner v-if="removingAsset" />
                        Remover
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

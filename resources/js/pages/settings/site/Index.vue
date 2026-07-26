<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import ImageUploadField from '@/components/ImageUploadField.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import { publish, unpublish, update } from '@/routes/settings/site';

type SiteContent = {
    title: string;
    description: string | null;
    hero_image_url: string | null;
    logo_url: string | null;
    favicon_url: string | null;
    primary_color: string | null;
    secondary_color: string | null;
    cta_text: string | null;
    cta_url: string | null;
    cta_secondary_text: string | null;
    cta_secondary_url: string | null;
    about_text: string | null;
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
    facebook_url: props.site?.facebook_url ?? '',
    instagram_url: props.site?.instagram_url ?? '',
    linkedin_url: props.site?.linkedin_url ?? '',
    footer_text: props.site?.footer_text ?? '',
    hero_image: null as File | null,
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
            <span class="text-sm text-muted-foreground">Status:</span>
            <Badge :variant="site?.is_published ? 'default' : 'secondary'">
                {{ site?.is_published ? 'Publicado' : 'Não publicado' }}
            </Badge>
        </div>

        <div v-if="contact" class="rounded-md border p-4 text-sm">
            <p class="font-medium">Contato exibido publicamente</p>
            <p class="text-muted-foreground">
                Vem da unidade matriz — para alterar, edite em
                <span class="font-medium text-foreground">Unidades</span>.
            </p>
            <ul class="mt-2 space-y-1 text-muted-foreground">
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
                label="Imagem principal"
                :current-url="site?.hero_image_url"
                helperText="Exibida no topo da página pública."
            />
            <InputError :message="form.errors.hero_image" />

            <ImageUploadField
                id="logo"
                v-model="form.logo"
                label="Logotipo"
                :current-url="site?.logo_url"
            />
            <InputError :message="form.errors.logo" />

            <ImageUploadField
                id="favicon"
                v-model="form.favicon"
                label="Favicon"
                :current-url="site?.favicon_url"
            />
            <InputError :message="form.errors.favicon" />

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
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import { update } from '@/routes/settings/seo';

type SeoData = {
    official_domain: string | null;
    schema_type: string;
    meta_title: string | null;
    meta_description: string | null;
    og_image_alt: string | null;
    focus_keywords: string | null;
    author_name: string | null;
    indexing_policy: string;
    google_search_console_verification: string | null;
    bing_webmaster_verification: string | null;
    google_business_profile_url: string | null;
    google_reviews_url: string | null;
    google_maps_url: string | null;
    latitude: string | null;
    longitude: string | null;
} | null;

type MarketingData = {
    integration_method: string;
    ga4_measurement_id: string | null;
    ga4_enabled: boolean;
    gtm_container_id: string | null;
    gtm_enabled: boolean;
    google_ads_conversion_id: string | null;
    google_ads_conversion_label: string | null;
    google_ads_enabled: boolean;
} | null;

const props = defineProps<{
    seo: SeoData;
    marketing: MarketingData;
    schemaTypeOptions: { value: string; label: string }[];
    indexingPolicyOptions: { value: string; label: string }[];
    integrationMethodOptions: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Presença digital' },
            { title: 'SEO e marketing' },
        ],
    },
});

const form = useForm({
    official_domain: props.seo?.official_domain ?? '',
    schema_type: props.seo?.schema_type ?? 'LocalBusiness',
    meta_title: props.seo?.meta_title ?? '',
    meta_description: props.seo?.meta_description ?? '',
    og_image_alt: props.seo?.og_image_alt ?? '',
    focus_keywords: props.seo?.focus_keywords ?? '',
    author_name: props.seo?.author_name ?? '',
    indexing_policy: props.seo?.indexing_policy ?? 'noindex',
    google_search_console_verification:
        props.seo?.google_search_console_verification ?? '',
    bing_webmaster_verification: props.seo?.bing_webmaster_verification ?? '',
    google_business_profile_url: props.seo?.google_business_profile_url ?? '',
    google_reviews_url: props.seo?.google_reviews_url ?? '',
    google_maps_url: props.seo?.google_maps_url ?? '',
    latitude: props.seo?.latitude ?? '',
    longitude: props.seo?.longitude ?? '',

    integration_method: props.marketing?.integration_method ?? 'none',
    ga4_measurement_id: props.marketing?.ga4_measurement_id ?? '',
    ga4_enabled: props.marketing?.ga4_enabled ?? false,
    gtm_container_id: props.marketing?.gtm_container_id ?? '',
    gtm_enabled: props.marketing?.gtm_enabled ?? false,
    google_ads_conversion_id: props.marketing?.google_ads_conversion_id ?? '',
    google_ads_conversion_label:
        props.marketing?.google_ads_conversion_label ?? '',
    google_ads_enabled: props.marketing?.google_ads_enabled ?? false,
});

function submit() {
    form.put(update().url, { preserveScroll: true });
}
</script>

<template>
    <Head title="SEO e marketing" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="SEO e marketing"
            description="Metadados para buscadores e configuração de integrações de marketing."
        />

        <form class="max-w-2xl space-y-8" @submit.prevent="submit">
            <section class="space-y-4">
                <h2 class="text-lg font-semibold">Identificação e domínio</h2>

                <div class="grid gap-2">
                    <Label for="official_domain"
                        >Domínio oficial (opcional)</Label
                    >
                    <Input
                        id="official_domain"
                        v-model="form.official_domain"
                        placeholder="clinicaexemplo.com.br"
                    />
                    <p class="text-sm text-muted-foreground">
                        Apenas o domínio, sem "https://". Em ambiente local, a
                        URL de desenvolvimento é usada no lugar deste campo.
                    </p>
                    <InputError :message="form.errors.official_domain" />
                </div>

                <div class="grid gap-2">
                    <Label for="schema_type">Tipo de negócio</Label>
                    <Select v-model="form.schema_type">
                        <SelectTrigger id="schema_type" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in schemaTypeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.schema_type" />
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-lg font-semibold">Metadados</h2>

                <div class="grid gap-2">
                    <Label for="meta_title">Título para buscadores</Label>
                    <Input id="meta_title" v-model="form.meta_title" />
                    <p class="text-sm text-muted-foreground">
                        Se vazio, usa o título da página inicial.
                    </p>
                    <InputError :message="form.errors.meta_title" />
                </div>

                <div class="grid gap-2">
                    <Label for="meta_description">
                        Descrição para buscadores
                    </Label>
                    <Textarea
                        id="meta_description"
                        v-model="form.meta_description"
                        rows="2"
                    />
                    <InputError :message="form.errors.meta_description" />
                </div>

                <div class="grid gap-2">
                    <Label for="og_image_alt">
                        Texto alternativo da imagem de compartilhamento
                    </Label>
                    <Input id="og_image_alt" v-model="form.og_image_alt" />
                    <InputError :message="form.errors.og_image_alt" />
                </div>

                <div class="grid gap-2">
                    <Label for="focus_keywords">
                        Palavras e temas estratégicos
                    </Label>
                    <Textarea
                        id="focus_keywords"
                        v-model="form.focus_keywords"
                        rows="2"
                    />
                    <p class="text-sm text-muted-foreground">
                        Não é a antiga meta tag "keywords" — serve só para
                        orientar títulos e conteúdo.
                    </p>
                    <InputError :message="form.errors.focus_keywords" />
                </div>

                <div class="grid gap-2">
                    <Label for="author_name">
                        Autor ou organização responsável
                    </Label>
                    <Input id="author_name" v-model="form.author_name" />
                    <InputError :message="form.errors.author_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="indexing_policy">Política de indexação</Label>
                    <Select v-model="form.indexing_policy">
                        <SelectTrigger id="indexing_policy" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in indexingPolicyOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-sm text-muted-foreground">
                        Em ambiente local, a indexação é sempre bloqueada,
                        independentemente desta configuração.
                    </p>
                    <InputError :message="form.errors.indexing_policy" />
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-lg font-semibold">
                    Verificações de ferramentas de busca
                </h2>

                <div class="grid gap-2">
                    <Label for="gsc">Google Search Console</Label>
                    <Input
                        id="gsc"
                        v-model="form.google_search_console_verification"
                    />
                    <InputError
                        :message="
                            form.errors.google_search_console_verification
                        "
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="bing">Bing Webmaster Tools</Label>
                    <Input
                        id="bing"
                        v-model="form.bing_webmaster_verification"
                    />
                    <InputError
                        :message="form.errors.bing_webmaster_verification"
                    />
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-lg font-semibold">
                    Google Business e localização
                </h2>

                <div class="grid gap-2">
                    <Label for="gbp">URL do perfil no Google Business</Label>
                    <Input
                        id="gbp"
                        v-model="form.google_business_profile_url"
                        type="url"
                    />
                    <InputError
                        :message="form.errors.google_business_profile_url"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="reviews">URL de avaliações</Label>
                    <Input
                        id="reviews"
                        v-model="form.google_reviews_url"
                        type="url"
                    />
                    <InputError :message="form.errors.google_reviews_url" />
                </div>

                <div class="grid gap-2">
                    <Label for="maps">URL de "como chegar"</Label>
                    <Input
                        id="maps"
                        v-model="form.google_maps_url"
                        type="url"
                    />
                    <InputError :message="form.errors.google_maps_url" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="latitude">Latitude (opcional)</Label>
                        <Input
                            id="latitude"
                            v-model="form.latitude"
                            type="number"
                            step="any"
                        />
                        <InputError :message="form.errors.latitude" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="longitude">Longitude (opcional)</Label>
                        <Input
                            id="longitude"
                            v-model="form.longitude"
                            type="number"
                            step="any"
                        />
                        <InputError :message="form.errors.longitude" />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-lg font-semibold">Marketing</h2>
                <p class="text-sm text-muted-foreground">
                    Nenhum script é ativado sem configuração válida e sem marcar
                    a opção correspondente como ativa.
                </p>

                <div class="grid gap-2">
                    <Label for="integration_method">
                        Mecanismo de integração
                    </Label>
                    <Select v-model="form.integration_method">
                        <SelectTrigger id="integration_method" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in integrationMethodOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-sm text-muted-foreground">
                        Evite instalar Google Tag e Google Tag Manager ao mesmo
                        tempo sem uma estratégia definida.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="ga4_measurement_id">
                        GA4 — Measurement ID
                    </Label>
                    <Input
                        id="ga4_measurement_id"
                        v-model="form.ga4_measurement_id"
                        placeholder="G-XXXXXXXXXX"
                    />
                    <InputError :message="form.errors.ga4_measurement_id" />
                    <Label class="flex items-center gap-2 font-normal">
                        <Checkbox v-model:model-value="form.ga4_enabled" />
                        Ativar GA4
                    </Label>
                </div>

                <div class="grid gap-2">
                    <Label for="gtm_container_id">
                        Google Tag Manager — Container ID
                    </Label>
                    <Input
                        id="gtm_container_id"
                        v-model="form.gtm_container_id"
                        placeholder="GTM-XXXXXXX"
                    />
                    <InputError :message="form.errors.gtm_container_id" />
                    <Label class="flex items-center gap-2 font-normal">
                        <Checkbox v-model:model-value="form.gtm_enabled" />
                        Ativar GTM
                    </Label>
                </div>

                <div class="grid gap-2">
                    <Label for="google_ads_conversion_id">
                        Google Ads — Conversion ID
                    </Label>
                    <Input
                        id="google_ads_conversion_id"
                        v-model="form.google_ads_conversion_id"
                        placeholder="AW-XXXXXXXXX"
                    />
                    <InputError
                        :message="form.errors.google_ads_conversion_id"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="google_ads_conversion_label">
                        Google Ads — Conversion Label
                    </Label>
                    <Input
                        id="google_ads_conversion_label"
                        v-model="form.google_ads_conversion_label"
                    />
                    <InputError
                        :message="form.errors.google_ads_conversion_label"
                    />
                    <Label class="flex items-center gap-2 font-normal">
                        <Checkbox
                            v-model:model-value="form.google_ads_enabled"
                        />
                        Ativar Google Ads
                    </Label>
                </div>
            </section>

            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Salvar
            </Button>
        </form>
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { update } from '@/routes/settings/organization';
import type { Organization } from '@/types/organization';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Configurações da clínica' },
            { title: 'Dados da clínica' },
        ],
    },
});

const props = defineProps<{
    organization: Organization;
    isOwner: boolean;
}>();

const form = useForm({
    name: props.organization.name,
    default_timezone: props.organization.default_timezone,
    default_currency: props.organization.default_currency,
    locale: props.organization.locale,
    primary_color: props.organization.primary_color ?? '',
    secondary_color: props.organization.secondary_color ?? '',
});

function submit() {
    form.put(update().url);
}
</script>

<template>
    <Head title="Organização" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Organização"
            description="Dados gerais da sua organização"
        />

        <p v-if="!isOwner" class="text-sm text-muted-foreground">
            Somente o proprietário da organização pode alterar estes dados.
        </p>

        <form class="grid max-w-xl gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="org-name">Nome</Label>
                <Input id="org-name" v-model="form.name" :disabled="!isOwner" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="org-timezone">Fuso horário</Label>
                <Input
                    id="org-timezone"
                    v-model="form.default_timezone"
                    :disabled="!isOwner"
                />
                <InputError :message="form.errors.default_timezone" />
            </div>

            <div class="grid gap-2">
                <Label for="org-currency">Moeda</Label>
                <Input
                    id="org-currency"
                    v-model="form.default_currency"
                    maxlength="3"
                    :disabled="!isOwner"
                />
                <InputError :message="form.errors.default_currency" />
            </div>

            <div class="grid gap-2">
                <Label for="org-locale">Idioma</Label>
                <Input
                    id="org-locale"
                    v-model="form.locale"
                    :disabled="!isOwner"
                />
                <InputError :message="form.errors.locale" />
            </div>

            <Button
                v-if="isOwner"
                type="submit"
                class="w-fit"
                :disabled="form.processing"
                >Salvar</Button
            >
        </form>
    </div>
</template>

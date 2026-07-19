<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index, update } from '@/routes/settings/legal-entities';
import type { LegalEntity } from '@/types/organization';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Configurações da clínica' },
            { title: 'Entidades legais', href: index() },
            { title: 'Editar entidade legal' },
        ],
    },
});

const props = defineProps<{
    legalEntity: LegalEntity;
}>();

const form = useForm({
    legal_name: props.legalEntity.legal_name,
    trade_name: props.legalEntity.trade_name ?? '',
    state_registration: props.legalEntity.state_registration ?? '',
    municipal_registration: props.legalEntity.municipal_registration ?? '',
    email: props.legalEntity.email ?? '',
    phone: props.legalEntity.phone ?? '',
});

function submit() {
    form.put(update(props.legalEntity.id).url);
}
</script>

<template>
    <Head title="Editar entidade legal" />

    <div class="flex flex-col space-y-6">
        <div>
            <h1 class="text-xl font-semibold">Editar entidade legal</h1>
            <p class="text-sm text-muted-foreground">
                {{ legalEntity.document }}
            </p>
        </div>

        <form class="grid max-w-xl gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="legal-name">
                    {{
                        legalEntity.type === 'individual'
                            ? 'Nome completo'
                            : 'Razão social'
                    }}
                </Label>
                <Input id="legal-name" v-model="form.legal_name" autofocus />
                <InputError :message="form.errors.legal_name" />
            </div>

            <div v-if="legalEntity.type === 'company'" class="grid gap-2">
                <Label for="trade-name">Nome fantasia (opcional)</Label>
                <Input id="trade-name" v-model="form.trade_name" />
                <InputError :message="form.errors.trade_name" />
            </div>

            <div v-if="legalEntity.type === 'company'" class="grid gap-2">
                <Label for="state-registration"
                    >Inscrição estadual (opcional)</Label
                >
                <Input
                    id="state-registration"
                    v-model="form.state_registration"
                />
                <InputError :message="form.errors.state_registration" />
            </div>

            <div v-if="legalEntity.type === 'company'" class="grid gap-2">
                <Label for="municipal-registration"
                    >Inscrição municipal (opcional)</Label
                >
                <Input
                    id="municipal-registration"
                    v-model="form.municipal_registration"
                />
                <InputError :message="form.errors.municipal_registration" />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-mail (opcional)</Label>
                <Input id="email" type="email" v-model="form.email" />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="phone">Telefone (opcional)</Label>
                <Input id="phone" v-model="form.phone" />
                <InputError :message="form.errors.phone" />
            </div>

            <Button type="submit" class="w-fit" :disabled="form.processing"
                >Salvar</Button
            >
        </form>
    </div>
</template>

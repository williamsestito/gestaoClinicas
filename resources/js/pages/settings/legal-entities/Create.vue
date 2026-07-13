<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import AddressFields from '@/components/organization/AddressFields.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { maskCpfCnpj } from '@/lib/masks';
import { store } from '@/routes/settings/legal-entities';
import type { AddressForm } from '@/types/organization';

const props = defineProps<{
    legalEntityTypes: { value: string; label: string }[];
    states: string[];
}>();

const form = useForm({
    type: 'individual',
    document: '',
    legal_name: '',
    trade_name: '',
    state_registration: '',
    municipal_registration: '',
    email: '',
    phone: '',
    address: {
        postal_code: '',
        street: '',
        number: '',
        complement: '',
        neighborhood: '',
        city: '',
        state: '',
    } as AddressForm,
});

const selectedTypeLabel = computed(
    () =>
        props.legalEntityTypes.find((t) => t.value === form.type)?.label ?? '',
);

function submit() {
    form.post(store().url);
}
</script>

<template>
    <Head title="Nova entidade legal" />

    <div class="flex flex-col space-y-6">
        <div>
            <h1 class="text-xl font-semibold">Nova entidade legal</h1>
            <p class="text-sm text-muted-foreground">
                Cadastre uma nova entidade legal (CPF/CNPJ) da sua clínica.
            </p>
        </div>

        <form class="grid max-w-xl gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label>Tipo</Label>
                <div class="flex gap-4">
                    <label
                        v-for="type in legalEntityTypes"
                        :key="type.value"
                        class="flex items-center gap-2 text-sm"
                    >
                        <input
                            type="radio"
                            name="type"
                            :value="type.value"
                            v-model="form.type"
                        />
                        {{ type.label }}
                    </label>
                </div>
                <InputError :message="form.errors.type" />
            </div>

            <div class="grid gap-2">
                <Label for="document">{{ selectedTypeLabel }}</Label>
                <Input
                    id="document"
                    :model-value="form.document"
                    placeholder="Somente números ou com máscara"
                    @update:model-value="
                        (value) =>
                            (form.document = maskCpfCnpj(
                                String(value),
                                form.type as 'individual' | 'company',
                            ))
                    "
                />
                <InputError :message="form.errors.document" />
            </div>

            <div class="grid gap-2">
                <Label for="legal-name">
                    {{
                        form.type === 'individual'
                            ? 'Nome completo'
                            : 'Razão social'
                    }}
                </Label>
                <Input id="legal-name" v-model="form.legal_name" autofocus />
                <InputError :message="form.errors.legal_name" />
            </div>

            <div v-if="form.type === 'company'" class="grid gap-2">
                <Label for="trade-name">Nome fantasia (opcional)</Label>
                <Input id="trade-name" v-model="form.trade_name" />
                <InputError :message="form.errors.trade_name" />
            </div>

            <div v-if="form.type === 'company'" class="grid gap-2">
                <Label for="state-registration"
                    >Inscrição estadual (opcional)</Label
                >
                <Input
                    id="state-registration"
                    v-model="form.state_registration"
                />
                <InputError :message="form.errors.state_registration" />
            </div>

            <div v-if="form.type === 'company'" class="grid gap-2">
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

            <AddressFields
                v-model="form.address"
                :states="states"
                :errors="{
                    postal_code: form.errors['address.postal_code'],
                    street: form.errors['address.street'],
                    number: form.errors['address.number'],
                    complement: form.errors['address.complement'],
                    neighborhood: form.errors['address.neighborhood'],
                    city: form.errors['address.city'],
                    state: form.errors['address.state'],
                }"
            />

            <Button type="submit" class="w-fit" :disabled="form.processing"
                >Salvar</Button
            >
        </form>
    </div>
</template>

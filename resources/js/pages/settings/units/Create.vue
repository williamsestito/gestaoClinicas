<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import AddressFields from '@/components/organization/AddressFields.vue';
import OpeningHoursFields from '@/components/organization/OpeningHoursFields.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AddressForm, OpeningHourForm } from '@/types/organization';

defineProps<{
    states: string[];
}>();

const form = useForm({
    name: '',
    code: '',
    phone: '',
    whatsapp: '',
    address: {
        postal_code: '',
        street: '',
        number: '',
        complement: '',
        neighborhood: '',
        city: '',
        state: '',
    } as AddressForm,
    opening_hours: [] as OpeningHourForm[],
});

function submit() {
    form.post('/settings/units');
}
</script>

<template>
    <Head title="Nova unidade" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Nova unidade"
            description="Cadastre uma nova unidade da organização"
        />

        <form class="grid max-w-xl gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="unit-name">Nome</Label>
                <Input id="unit-name" v-model="form.name" autofocus />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="unit-code"
                    >Código (opcional — gerado automaticamente se vazio)</Label
                >
                <Input id="unit-code" v-model="form.code" />
                <InputError :message="form.errors.code" />
            </div>

            <div class="grid gap-2">
                <Label for="unit-phone">Telefone (opcional)</Label>
                <Input id="unit-phone" v-model="form.phone" />
                <InputError :message="form.errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="unit-whatsapp">WhatsApp (opcional)</Label>
                <Input id="unit-whatsapp" v-model="form.whatsapp" />
                <InputError :message="form.errors.whatsapp" />
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

            <OpeningHoursFields
                v-model="form.opening_hours"
                :error="form.errors.opening_hours"
            />

            <Button type="submit" class="w-fit" :disabled="form.processing"
                >Criar unidade</Button
            >
        </form>
    </div>
</template>

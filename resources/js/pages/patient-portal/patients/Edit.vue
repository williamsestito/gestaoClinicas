<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import AddressFields from '@/components/organization/AddressFields.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { maskCpf } from '@/lib/masks';
import patientPortal from '@/routes/patient-portal';
import type { AddressForm } from '@/types/organization';

const props = defineProps<{
    patient: {
        id: string;
        name: string;
        preferred_name: string | null;
        document: string | null;
        birth_date: string;
        phone: string | null;
        whatsapp: string | null;
        email: string | null;
    };
    address: AddressForm | null;
    states: string[];
}>();

defineOptions({
    layout: {
        title: 'Meus dados',
    },
});

function emptyAddress(): AddressForm {
    return {
        postal_code: '',
        street: '',
        number: '',
        complement: '',
        neighborhood: '',
        city: '',
        state: '',
    };
}

const form = useForm({
    name: props.patient.name,
    preferred_name: props.patient.preferred_name ?? '',
    document: props.patient.document ?? '',
    phone: props.patient.phone ?? '',
    whatsapp: props.patient.whatsapp ?? '',
    email: props.patient.email ?? '',
    address: props.address ?? emptyAddress(),
});

function submit() {
    form.put(patientPortal.patients.update(props.patient.id).url);
}
</script>

<template>
    <Head title="Meus dados" />

    <form class="grid gap-6" @submit.prevent="submit">
        <div>
            <h2 class="text-lg font-medium">{{ patient.name }}</h2>
            <p class="text-sm text-muted-foreground">
                Nascimento: {{ patient.birth_date }}
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="name">Nome</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="preferred_name">Nome social (opcional)</Label>
                <Input id="preferred_name" v-model="form.preferred_name" />
                <InputError :message="form.errors.preferred_name" />
            </div>
            <div class="grid gap-2">
                <Label for="document">CPF (opcional)</Label>
                <Input
                    id="document"
                    :model-value="form.document"
                    @update:model-value="
                        (v) => (form.document = maskCpf(String(v)))
                    "
                />
                <InputError :message="form.errors.document" />
            </div>
            <div class="grid gap-2">
                <Label for="phone">Telefone (opcional)</Label>
                <PhoneInput id="phone" v-model="form.phone" />
                <InputError :message="form.errors.phone" />
            </div>
            <div class="grid gap-2">
                <Label for="whatsapp">WhatsApp (opcional)</Label>
                <PhoneInput id="whatsapp" v-model="form.whatsapp" />
                <InputError :message="form.errors.whatsapp" />
            </div>
            <div class="grid gap-2">
                <Label for="email">E-mail de contato (opcional)</Label>
                <Input id="email" v-model="form.email" type="email" />
                <InputError :message="form.errors.email" />
            </div>
        </div>

        <div>
            <h3 class="mb-4 text-sm font-medium">Endereço (opcional)</h3>
            <AddressFields v-model="form.address" :states="states" />
        </div>

        <Button
            type="submit"
            class="w-fit"
            :disabled="form.processing"
            data-test="patient-profile-save-button"
        >
            <Spinner v-if="form.processing" />
            Salvar
        </Button>
    </form>
</template>

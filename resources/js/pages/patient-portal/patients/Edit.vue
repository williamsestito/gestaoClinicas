<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IdCard, MapPin, Phone, User } from '@lucide/vue';
import ImageUploadField from '@/components/ImageUploadField.vue';
import InputError from '@/components/InputError.vue';
import AddressFields from '@/components/organization/AddressFields.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { initials } from '@/lib/initials';
import { formatDateBr, maskCpf } from '@/lib/masks';
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
        photo_url: string | null;
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

const photoForm = useForm({ photo: null as File | null });

function submitPhoto() {
    if (!photoForm.photo) {
        return;
    }

    photoForm.post(patientPortal.patients.photo.update(props.patient.id).url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            photoForm.reset();
        },
    });
}
</script>

<template>
    <Head title="Meus dados" />

    <div class="grid gap-6">
        <Card>
            <CardContent
                class="flex flex-col items-center gap-4 py-6 text-center sm:flex-row sm:text-left"
            >
                <Avatar class="size-20 border border-border">
                    <AvatarImage
                        v-if="patient.photo_url"
                        :src="patient.photo_url"
                        :alt="patient.name"
                    />
                    <AvatarFallback class="text-lg">
                        {{ initials(patient.name) }}
                    </AvatarFallback>
                </Avatar>
                <div>
                    <h1 class="text-xl font-semibold">{{ patient.name }}</h1>
                    <p class="text-sm text-muted-foreground">
                        Nascimento: {{ formatDateBr(patient.birth_date) }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Mantenha seus dados sempre atualizados — é assim que a
                        clínica entra em contato com você.
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <User class="size-4 text-muted-foreground" />
                    Foto de perfil
                </CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4">
                <ImageUploadField
                    id="patient-photo"
                    v-model="photoForm.photo"
                    label="Foto"
                    :current-url="patient.photo_url"
                    helper-text="JPEG, PNG ou WEBP, até 2 MB. Visível só para você e a equipe da clínica."
                />
                <InputError :message="photoForm.errors.photo" />
                <Button
                    type="button"
                    class="w-fit"
                    :disabled="!photoForm.photo || photoForm.processing"
                    @click="submitPhoto"
                >
                    <Spinner v-if="photoForm.processing" />
                    Salvar foto
                </Button>
            </CardContent>
        </Card>

        <form class="grid gap-6" @submit.prevent="submit">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <IdCard class="size-4 text-muted-foreground" />
                        Dados pessoais
                    </CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">Nome</Label>
                        <Input id="name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="preferred_name"
                            >Nome social (opcional)</Label
                        >
                        <Input
                            id="preferred_name"
                            v-model="form.preferred_name"
                        />
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
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Phone class="size-4 text-muted-foreground" />
                        Contato
                    </CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
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
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="email">E-mail de contato (opcional)</Label>
                        <Input id="email" v-model="form.email" type="email" />
                        <InputError :message="form.errors.email" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <MapPin class="size-4 text-muted-foreground" />
                        Endereço (opcional)
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <AddressFields v-model="form.address" :states="states" />
                </CardContent>
            </Card>

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
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import ImageUploadField from '@/components/ImageUploadField.vue';
import InputError from '@/components/InputError.vue';
import AddressFields from '@/components/organization/AddressFields.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { maskCpf } from '@/lib/masks';
import { edit as editProfile, update as updateProfile } from '@/routes/profile';
import { destroy as destroyPhoto, update as updatePhoto } from '@/routes/profile/photo';
import { edit as editSecurity } from '@/routes/security';
import { send } from '@/routes/verification';
import type { AddressForm } from '@/types/organization';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configurações de perfil',
                href: editProfile(),
            },
        ],
    },
});

const props = defineProps<{
    mustVerifyEmail: boolean;
    status?: string;
    states: string[];
    profile: {
        phone: string | null;
        cpf: string | null;
        photo_url: string | null;
        address: AddressForm;
    };
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const form = useForm({
    name: user.value.name,
    email: user.value.email,
    phone: props.profile.phone ?? '',
    cpf: props.profile.cpf ?? '',
    address_postal_code: props.profile.address.postal_code,
    address_street: props.profile.address.street,
    address_number: props.profile.address.number,
    address_complement: props.profile.address.complement,
    address_neighborhood: props.profile.address.neighborhood,
    address_city: props.profile.address.city,
    address_state: props.profile.address.state,
});

// AddressFields.vue espera um único objeto `AddressForm` (v-model), mas os
// campos de endereço do usuário são colunas soltas (address_street etc.,
// não uma relação separada) — este computed só faz a ponte entre as duas
// formas sem duplicar estado.
const addressModel = computed<AddressForm>({
    get: () => ({
        postal_code: form.address_postal_code,
        street: form.address_street,
        number: form.address_number,
        complement: form.address_complement,
        neighborhood: form.address_neighborhood,
        city: form.address_city,
        state: form.address_state,
    }),
    set: (value) => {
        form.address_postal_code = value.postal_code;
        form.address_street = value.street;
        form.address_number = value.number;
        form.address_complement = value.complement;
        form.address_neighborhood = value.neighborhood;
        form.address_city = value.city;
        form.address_state = value.state;
    },
});

function submit() {
    form.transform((data) => ({
        ...data,
        phone: data.phone || null,
        cpf: data.cpf ? data.cpf.replace(/\D/g, '') : null,
    })).patch(updateProfile().url, { preserveScroll: true });
}

const photoForm = useForm({ photo: null as File | null });

function onPhotoSelected(file: File | null) {
    photoForm.photo = file;

    if (!file) {
        return;
    }

    photoForm.post(updatePhoto().url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            photoForm.photo = null;
        },
    });
}

const removingPhoto = ref(false);

function removePhoto() {
    removingPhoto.value = true;
    router.delete(destroyPhoto().url, {
        preserveScroll: true,
        onFinish: () => {
            removingPhoto.value = false;
        },
    });
}
</script>

<template>
    <Head title="Configurações de perfil" />

    <h1 class="sr-only">Configurações de perfil</h1>

    <div class="flex flex-col space-y-8">
        <Heading
            variant="small"
            title="Perfil"
            description="Seus dados pessoais e como você acessa a plataforma"
        />

        <div class="max-w-2xl space-y-8">
            <div class="space-y-4">
                <p class="text-sm font-medium">Foto</p>
                <ImageUploadField
                    id="photo"
                    :model-value="photoForm.photo"
                    label="Foto do perfil"
                    :current-url="profile.photo_url"
                    helperText="Formatos aceitos: JPEG, PNG ou WebP. Tamanho máximo: 2 MB."
                    @update:model-value="onPhotoSelected"
                />
                <InputError :message="photoForm.errors.photo" />
                <Button
                    v-if="profile.photo_url"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:text-destructive"
                    :disabled="removingPhoto"
                    @click="removePhoto"
                >
                    Remover foto atual
                </Button>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Nome</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        required
                        autocomplete="name"
                        placeholder="Nome completo"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">E-mail</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="username"
                        placeholder="E-mail"
                    />
                    <InputError :message="form.errors.email" />
                    <p
                        v-if="form.isDirty && form.email !== user.email"
                        class="text-sm text-muted-foreground"
                    >
                        Ao salvar, você precisará confirmar o novo e-mail
                        novamente antes de usá-lo para entrar.
                    </p>
                </div>

                <div
                    v-if="mustVerifyEmail && !user.email_verified_at"
                >
                    <p class="text-sm text-muted-foreground">
                        Seu endereço de e-mail não foi verificado.
                        <Link
                            :href="send()"
                            as="button"
                            class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                        >
                            Clique aqui para reenviar o e-mail de verificação.
                        </Link>
                    </p>

                    <div
                        v-if="status === 'verification-link-sent'"
                        class="mt-2 text-sm font-medium text-green-600"
                    >
                        Um novo link de verificação foi enviado para o seu
                        endereço de e-mail.
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="phone">Telefone</Label>
                    <PhoneInput id="phone" v-model="form.phone" />
                    <InputError :message="form.errors.phone" />
                </div>

                <div class="grid gap-2 sm:max-w-70">
                    <Label for="cpf">CPF</Label>
                    <Input
                        id="cpf"
                        :model-value="form.cpf"
                        inputmode="numeric"
                        placeholder="000.000.000-00"
                        @update:model-value="
                            (value) => (form.cpf = maskCpf(String(value)))
                        "
                    />
                    <InputError :message="form.errors.cpf" />
                </div>

                <div class="space-y-4">
                    <p class="text-sm font-medium">Endereço</p>
                    <AddressFields
                        v-model="addressModel"
                        :states="states"
                        :errors="{
                            postal_code: form.errors.address_postal_code,
                            street: form.errors.address_street,
                            number: form.errors.address_number,
                            complement: form.errors.address_complement,
                            neighborhood: form.errors.address_neighborhood,
                            city: form.errors.address_city,
                            state: form.errors.address_state,
                        }"
                    />
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        :disabled="form.processing"
                        data-test="update-profile-button"
                    >
                        Salvar
                    </Button>
                </div>
            </form>

            <div class="rounded-md border p-4">
                <p class="text-sm font-medium">Senha</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Para alterar sua senha, dois fatores de autenticação ou
                    passkeys, acesse as configurações de segurança.
                </p>
                <Link :href="editSecurity()" class="mt-3 inline-block">
                    <Button variant="outline" size="sm"
                        >Ir para Segurança</Button
                    >
                </Link>
            </div>
        </div>
    </div>

    <DeleteUser />
</template>

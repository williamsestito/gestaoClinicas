<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/PatientPortal/RegisteredPatientUserController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { maskCpf } from '@/lib/masks';
import patientPortal from '@/routes/patient-portal';

defineOptions({
    layout: {
        title: 'Cadastro do paciente',
        description: 'Crie sua conta para acessar o portal do paciente',
    },
});

defineProps<{
    organizationConfigured: boolean;
}>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    registering_for: 'self' as 'self' | 'dependent',
    birth_date: '',
    document: '',
    phone: '',
    dependent_name: '',
    dependent_birth_date: '',
    dependent_document: '',
    dependent_phone: '',
    relationship: '',
    responsible_phone: '',
    website: '',
    form_rendered_at: Date.now(),
});

function submit() {
    form.post(store().url);
}
</script>

<template>
    <Head title="Cadastro do paciente" />

    <div
        v-if="!organizationConfigured"
        class="text-center text-sm text-muted-foreground"
    >
        Cadastro indisponível no momento.
    </div>

    <form v-else class="flex flex-col gap-6" @submit.prevent="submit">
        <!--
            Honeypot: invisível e inalcançável por teclado/leitor de tela
            para uma pessoa real, mas presente no DOM para bots de
            preenchimento automático — ver RegisteredPatientUserController.
        -->
        <div class="absolute left-[-9999px]" aria-hidden="true">
            <label for="website">Não preencha este campo</label>
            <input
                id="website"
                v-model="form.website"
                type="text"
                tabindex="-1"
                autocomplete="off"
            />
        </div>

        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Seu nome</Label>
                <Input id="name" v-model="form.name" required autofocus />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="email"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Senha</Label>
                <PasswordInput
                    id="password"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirmar senha</Label>
                <PasswordInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <div class="grid gap-2">
                <Label>Você está se cadastrando para</Label>
                <div class="grid grid-cols-2 gap-2">
                    <Button
                        type="button"
                        :variant="
                            form.registering_for === 'self'
                                ? 'default'
                                : 'outline'
                        "
                        @click="form.registering_for = 'self'"
                    >
                        Mim mesmo
                    </Button>
                    <Button
                        type="button"
                        :variant="
                            form.registering_for === 'dependent'
                                ? 'default'
                                : 'outline'
                        "
                        @click="form.registering_for = 'dependent'"
                    >
                        Um dependente
                    </Button>
                </div>
            </div>

            <template v-if="form.registering_for === 'self'">
                <div class="grid gap-2">
                    <Label for="birth_date">Data de nascimento</Label>
                    <Input
                        id="birth_date"
                        v-model="form.birth_date"
                        type="date"
                        required
                    />
                    <InputError :message="form.errors.birth_date" />
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
                </div>
            </template>

            <template v-else>
                <div class="grid gap-2">
                    <Label for="dependent_name">Nome do dependente</Label>
                    <Input
                        id="dependent_name"
                        v-model="form.dependent_name"
                        required
                    />
                    <InputError :message="form.errors.dependent_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="dependent_birth_date">
                        Data de nascimento do dependente
                    </Label>
                    <Input
                        id="dependent_birth_date"
                        v-model="form.dependent_birth_date"
                        type="date"
                        required
                    />
                    <InputError
                        :message="form.errors.dependent_birth_date"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="dependent_document">
                        CPF do dependente (opcional)
                    </Label>
                    <Input
                        id="dependent_document"
                        :model-value="form.dependent_document"
                        @update:model-value="
                            (v) =>
                                (form.dependent_document = maskCpf(String(v)))
                        "
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="relationship">
                        Sua relação com o dependente
                    </Label>
                    <Input
                        id="relationship"
                        v-model="form.relationship"
                        placeholder="Mãe, pai, tutor(a)..."
                        required
                    />
                    <InputError :message="form.errors.relationship" />
                </div>
                <div class="grid gap-2">
                    <Label for="responsible_phone">Seu telefone</Label>
                    <PhoneInput
                        id="responsible_phone"
                        v-model="form.responsible_phone"
                    />
                    <InputError :message="form.errors.responsible_phone" />
                </div>
            </template>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="form.processing"
                data-test="patient-register-button"
            >
                <Spinner v-if="form.processing" />
                Criar conta
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Já tem uma conta?
            <TextLink :href="patientPortal.login()">Entrar</TextLink>
        </div>
    </form>
</template>

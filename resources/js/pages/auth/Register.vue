<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import patientPortal from '@/routes/patient-portal';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Criar uma conta',
        description: 'Informe seus dados abaixo para criar sua conta',
    },
});

const accountType = ref<'clinic' | 'patient' | null>(null);
</script>

<template>
    <Head title="Cadastro" />

    <div v-if="accountType === null" class="grid gap-4">
        <Card
            class="cursor-pointer transition-colors hover:border-primary"
            data-test="account-type-clinic"
            @click="accountType = 'clinic'"
        >
            <CardContent class="py-4">
                <p class="font-medium">Criar uma conta para minha clínica</p>
                <p class="text-sm text-muted-foreground">
                    Cadastre sua clínica, empresa ou consultório para começar a
                    usar a plataforma.
                </p>
            </CardContent>
        </Card>

        <Link :href="patientPortal.register()" data-test="account-type-patient">
            <Card class="cursor-pointer transition-colors hover:border-primary">
                <CardContent class="py-4">
                    <p class="font-medium">Acessar como paciente</p>
                    <p class="text-sm text-muted-foreground">
                        Cadastre-se para acompanhar seus dados e os de seus
                        dependentes.
                    </p>
                </CardContent>
            </Card>
        </Link>

        <div class="text-center text-sm text-muted-foreground">
            Já tem uma conta?
            <TextLink :href="login()" class="underline underline-offset-4"
                >Entrar</TextLink
            >
        </div>
    </div>

    <Form
        v-else
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Nome</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Nome completo"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Senha</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Senha"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirmar senha</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirmar senha"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Criar conta
            </Button>
        </div>

        <div
            class="flex justify-center gap-1 text-center text-sm text-muted-foreground"
        >
            <button
                type="button"
                class="underline underline-offset-4"
                @click="accountType = null"
            >
                Voltar
            </button>
            ·
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="6"
                >Já tem uma conta? Entrar</TextLink
            >
        </div>
    </Form>
</template>

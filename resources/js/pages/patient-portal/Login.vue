<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import patientPortal from '@/routes/patient-portal';
import { store as loginStore } from '@/routes/patient-portal/login';

defineOptions({
    layout: {
        title: 'Portal do paciente',
        description: 'Informe seu e-mail e senha para entrar',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Entrar no portal do paciente" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        v-bind="loginStore.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Senha</Label>
                    <TextLink
                        :href="patientPortal.password.request()"
                        class="text-sm"
                    >
                        Esqueceu sua senha?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Senha"
                />
                <InputError :message="errors.password" />
            </div>

            <Label for="remember" class="flex items-center space-x-3">
                <Checkbox id="remember" name="remember" />
                <span>Lembrar de mim</span>
            </Label>

            <Button
                type="submit"
                class="mt-4 w-full"
                :disabled="processing"
                data-test="patient-login-button"
            >
                <Spinner v-if="processing" />
                Entrar
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Não tem uma conta?
            <TextLink :href="patientPortal.register()">Cadastre-se</TextLink>
        </div>
    </Form>
</template>

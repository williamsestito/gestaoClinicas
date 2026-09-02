<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import patientPortal from '@/routes/patient-portal';

defineOptions({
    layout: {
        title: 'Entrar na sua conta',
        description: 'Informe seu e-mail e senha abaixo para entrar',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

/**
 * Esta tela é compartilhada entre login de clínica/staff e de paciente
 * (ver routes/patient-portal.php). Quando o link "Acessar o portal do
 * paciente" da landing pública (LandingSchedulingSection.vue) traz os
 * dados do pré-agendamento na query string, é porque quem chegou aqui é
 * um paciente — "Cadastre-se" pula direto para o formulário do portal
 * (patient-portal.register), já pré-preenchido, em vez do seletor
 * genérico "clínica ou paciente" (achado real: a pessoa tentava entrar
 * sem ter conta ainda, e não encontrava um caminho direto de volta ao
 * cadastro certo).
 */
const patientPrefillQuery = computed<Record<string, string> | null>(() => {
    if (typeof window === 'undefined') {
        return null;
    }

    const params = new URLSearchParams(window.location.search);
    const query: Record<string, string> = {};

    for (const key of ['name', 'phone', 'email', 'document']) {
        const value = params.get(key);

        if (value) {
            query[key] = value;
        }
    }

    return Object.keys(query).length > 0 ? query : null;
});

const registerHref = computed(() => {
    if (patientPrefillQuery.value) {
        return patientPortal.register({ query: patientPrefillQuery.value }).url;
    }

    return register().url;
});
</script>

<template>
    <Head title="Entrar" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
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
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Senha</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Esqueceu sua senha?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Senha"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Lembrar de mim</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Entrar
            </Button>
        </div>

        <div class="text-muted-foreground text-center text-sm">
            Não tem uma conta?
            <TextLink :href="registerHref" :tabindex="5">Cadastre-se</TextLink>
        </div>
    </Form>
</template>

<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { send as sendPasswordResetLink } from '@/routes/forgot-password';
import { directConfirmEmail, directReset } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Esqueceu sua senha',
        description:
            'Informe seu e-mail para receber um link de redefinição de senha',
    },
});

const props = defineProps<{
    status?: string;
    directPasswordResetEnabled: boolean;
    confirmedEmail?: string | null;
    passwordRules?: string;
}>();

const inputEmail = ref(props.confirmedEmail ?? '');
</script>

<template>
    <Head title="Esqueceu sua senha" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div
        v-if="directPasswordResetEnabled"
        class="mb-4 rounded-md border border-dashed border-amber-500/50 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400"
    >
        Modo desenvolvimento: a redefinição é feita direto, sem envio de e-mail.
        Esse atalho não existe fora de ambiente local/testing.
    </div>

    <!-- Passo 2 (dev): e-mail já confirmado, define a nova senha direto -->
    <div v-if="directPasswordResetEnabled && confirmedEmail" class="space-y-6">
        <Form
            v-bind="directReset.form()"
            :transform="(data) => ({ ...data, email: confirmedEmail })"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">E-mail</Label>
                    <Input
                        id="email"
                        type="email"
                        v-model="inputEmail"
                        readonly
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Nova senha</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        autofocus
                        placeholder="Senha"
                        :passwordrules="passwordRules"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">
                        Confirmar nova senha
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                        placeholder="Confirmar senha"
                        :passwordrules="passwordRules"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="processing"
                    data-test="direct-reset-password-button"
                >
                    <Spinner v-if="processing" />
                    Salvar nova senha
                </Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <TextLink :href="login()">Usar outro e-mail</TextLink>
        </div>
    </div>

    <!-- Passo 1 (dev): confirma que o e-mail existe -->
    <div v-else-if="directPasswordResetEnabled" class="space-y-6">
        <Form
            v-bind="directConfirmEmail.form()"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    Continuar
                </Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <span>Ou, volte para</span>
            <TextLink :href="login()">entrar</TextLink>
        </div>
    </div>

    <!-- Fluxo padrão (produção): link de redefinição por e-mail — envia
         para App\Http\Controllers\Auth\SendPasswordResetLinkController, que
         reconhece conta de staff ou de paciente pelo e-mail. -->
    <div v-else class="space-y-6">
        <Form
            v-bind="sendPasswordResetLink.form()"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    Enviar link de redefinição de senha
                </Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <span>Ou, volte para</span>
            <TextLink :href="login()">entrar</TextLink>
        </div>
    </div>
</template>

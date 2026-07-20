<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/invitations';

defineProps<{
    valid: boolean;
    organizationName: string | null;
    email: string | null;
    token: string;
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Aceitar convite',
        description: 'Crie sua senha para concluir o acesso',
    },
});
</script>

<template>
    <Head title="Aceitar convite" />

    <div v-if="!valid" class="space-y-4 text-center">
        <p class="text-sm text-muted-foreground">
            Este convite não é mais válido — pode já ter sido usado, cancelado
            ou expirado. Solicite um novo convite ao administrador da clínica.
        </p>
        <TextLink :href="login()">Voltar para o login</TextLink>
    </div>

    <Form
        v-else
        v-bind="store.form(token)"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <p class="text-sm text-muted-foreground">
            Você foi convidado para fazer parte de
            <strong>{{ organizationName }}</strong
            >. Crie sua senha para concluir o acesso.
        </p>

        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input
                    id="email"
                    type="email"
                    :model-value="email ?? undefined"
                    readonly
                />
            </div>

            <div class="grid gap-2">
                <Label for="name">Nome</Label>
                <Input
                    id="name"
                    name="name"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Nome completo"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Senha</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Senha"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <Button
                type="submit"
                class="w-full"
                :disabled="processing"
                data-test="accept-invitation-button"
            >
                <Spinner v-if="processing" />
                Aceitar convite e entrar
            </Button>
        </div>
    </Form>
</template>

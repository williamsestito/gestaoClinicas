<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Card, CardContent } from '@/components/ui/card';
import { login } from '@/routes';
import patientPortal from '@/routes/patient-portal';

defineOptions({
    layout: {
        title: 'Criar uma conta',
        description: 'Informe seus dados abaixo para criar sua conta',
    },
});
</script>

<template>
    <Head title="Cadastro" />

    <div class="grid gap-4">
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
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard, login, register } from '@/routes';
</script>

<template>
    <Head title="Gestão de Clínicas" />

    <div
        class="flex min-h-screen flex-col items-center justify-center gap-8 bg-background p-6 text-foreground"
    >
        <Card class="w-full max-w-md">
            <CardHeader class="text-center">
                <CardTitle class="text-2xl">Gestão de Clínicas</CardTitle>
                <CardDescription>
                    Esta aplicação está em desenvolvimento. A fundação técnica
                    está ativa; os módulos de negócio serão adicionados nas
                    próximas fases.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col gap-3">
                <Link v-if="$page.props.auth.user" :href="dashboard()">
                    <Button class="w-full">Acessar dashboard</Button>
                </Link>
                <template v-else>
                    <Link :href="login()">
                        <Button class="w-full">Entrar</Button>
                    </Link>
                    <Link :href="register()">
                        <Button variant="outline" class="w-full"
                            >Criar conta</Button
                        >
                    </Link>
                </template>
            </CardContent>
        </Card>
    </div>
</template>

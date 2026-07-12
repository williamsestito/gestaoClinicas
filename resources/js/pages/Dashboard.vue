<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

defineProps<{
    appEnvironment: string;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <Card>
            <CardHeader>
                <CardTitle>Fundação técnica ativa</CardTitle>
                <CardDescription>
                    A infraestrutura base da plataforma (autenticação, banco de
                    dados, fila, cache e painel administrativo) está configurada
                    e em funcionamento.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">Usuário</p>
                    <p class="font-medium">{{ user.name }}</p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">E-mail</p>
                    <p class="font-medium">{{ user.email }}</p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">
                        Status de verificação
                    </p>
                    <Badge
                        :variant="
                            user.email_verified_at ? 'default' : 'destructive'
                        "
                    >
                        {{
                            user.email_verified_at
                                ? 'E-mail verificado'
                                : 'E-mail não verificado'
                        }}
                    </Badge>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">Ambiente atual</p>
                    <Badge variant="secondary">{{ appEnvironment }}</Badge>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

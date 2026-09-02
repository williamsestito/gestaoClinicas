<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import AuthSimpleLayout from '@/layouts/auth/AuthSimpleLayout.vue';
import { dashboard } from '@/routes';

const props = defineProps<{
    status: number;
}>();

const TITLES: Record<number, string> = {
    403: 'Acesso não autorizado',
    404: 'Página não encontrada',
    419: 'Sessão expirada',
    500: 'Erro no servidor',
    503: 'Manutenção em andamento',
};

const DESCRIPTIONS: Record<number, string> = {
    403: 'Você não tem permissão para realizar essa ação. Se acha que isso é um engano, fale com um administrador da clínica.',
    404: 'A página que você procura não existe ou foi movida.',
    419: 'Sua sessão expirou. Recarregue a página e tente novamente.',
    500: 'Algo deu errado do nosso lado. Tente novamente em instantes.',
    503: 'Estamos em manutenção rápida. Volte em alguns minutos.',
};

const title = computed(() => TITLES[props.status] ?? 'Algo deu errado');
const description = computed(
    () =>
        DESCRIPTIONS[props.status] ??
        'Não foi possível concluir essa ação agora.',
);
</script>

<template>
    <Head :title="`Erro ${status}`" />

    <AuthSimpleLayout
        :title="`${status} — ${title}`"
        :description="description"
    >
        <Link :href="dashboard()">
            <Button class="w-full">Voltar ao início</Button>
        </Link>
    </AuthSimpleLayout>
</template>

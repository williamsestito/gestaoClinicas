<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/composables/useAppearance';

// Alternância direta de 1 clique (claro <-> escuro), não um menu com 3
// opções — um botão de navbar que exige abrir um menu para trocar de tema
// é percebido por quem usa a aplicação como "não funciona". A opção
// "sistema" continua disponível na página de configurações de aparência
// (AppearanceTabs.vue), só não faz parte deste atalho rápido.
const { resolvedAppearance, updateAppearance } = useAppearance();

const isDark = computed(() => resolvedAppearance.value === 'dark');
const nextLabel = computed(() =>
    isDark.value ? 'Mudar para tema claro' : 'Mudar para tema escuro',
);

function toggle() {
    updateAppearance(isDark.value ? 'light' : 'dark');
}
</script>

<template>
    <Tooltip>
        <TooltipTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                :aria-label="nextLabel"
                @click="toggle"
            >
                <Sun v-if="isDark" class="size-4" />
                <Moon v-else class="size-4" />
            </Button>
        </TooltipTrigger>
        <TooltipContent>{{ nextLabel }}</TooltipContent>
    </Tooltip>
</template>

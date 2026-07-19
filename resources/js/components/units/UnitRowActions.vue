<script setup lang="ts">
import { MoreHorizontal } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Unit } from '@/types/organization';

withDefaults(
    defineProps<{
        unit: Unit;
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const emit = defineEmits<{
    edit: [];
    toggleStatus: [];
    makeHeadquarters: [];
    delete: [];
    restore: [];
}>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                :disabled="disabled"
                :aria-label="`Ações para ${unit.name}`"
            >
                <MoreHorizontal class="size-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <template v-if="unit.deleted_at">
                <DropdownMenuItem @select="emit('restore')">
                    Restaurar
                </DropdownMenuItem>
            </template>
            <template v-else>
                <DropdownMenuItem @select="emit('edit')">
                    Editar
                </DropdownMenuItem>
                <DropdownMenuItem @select="emit('toggleStatus')">
                    {{ unit.status === 'active' ? 'Inativar' : 'Ativar' }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="!unit.is_headquarters && unit.status === 'active'"
                    @select="emit('makeHeadquarters')"
                >
                    Definir como matriz
                </DropdownMenuItem>
                <template v-if="!unit.is_headquarters">
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        @select="emit('delete')"
                    >
                        Excluir
                    </DropdownMenuItem>
                </template>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

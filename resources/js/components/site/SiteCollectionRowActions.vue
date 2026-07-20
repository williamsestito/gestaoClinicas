<script setup lang="ts">
import { ArrowDown, ArrowUp, MoreHorizontal } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

withDefaults(
    defineProps<{
        label: string;
        isActive: boolean;
        canMoveUp: boolean;
        canMoveDown: boolean;
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const emit = defineEmits<{
    edit: [];
    toggleActive: [];
    moveUp: [];
    moveDown: [];
    delete: [];
}>();
</script>

<template>
    <div class="flex items-center justify-end gap-1">
        <Button
            variant="ghost"
            size="icon"
            :disabled="disabled || !canMoveUp"
            :aria-label="`Mover ${label} para cima`"
            @click="emit('moveUp')"
        >
            <ArrowUp class="size-4" />
        </Button>
        <Button
            variant="ghost"
            size="icon"
            :disabled="disabled || !canMoveDown"
            :aria-label="`Mover ${label} para baixo`"
            @click="emit('moveDown')"
        >
            <ArrowDown class="size-4" />
        </Button>
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    variant="ghost"
                    size="icon"
                    :disabled="disabled"
                    :aria-label="`Ações para ${label}`"
                >
                    <MoreHorizontal class="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem @select="emit('edit')">
                    Editar
                </DropdownMenuItem>
                <DropdownMenuItem @select="emit('toggleActive')">
                    {{ isActive ? 'Inativar' : 'Ativar' }}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    variant="destructive"
                    @select="emit('delete')"
                >
                    Excluir
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>

<script setup lang="ts">
import { MoreHorizontal } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export type ActionableSpecialty = {
    id: string;
    name: string;
    status: 'active' | 'inactive';
    deleted_at: string | null;
};

withDefaults(
    defineProps<{
        specialty: ActionableSpecialty;
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const emit = defineEmits<{
    edit: [];
    activate: [];
    deactivate: [];
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
                :aria-label="`Ações para ${specialty.name}`"
            >
                <MoreHorizontal class="size-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <template v-if="specialty.deleted_at">
                <DropdownMenuItem @select="emit('restore')">
                    Restaurar
                </DropdownMenuItem>
            </template>
            <template v-else>
                <DropdownMenuItem @select="emit('edit')">
                    Editar
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="specialty.status === 'active'"
                    @select="emit('deactivate')"
                >
                    Inativar
                </DropdownMenuItem>
                <DropdownMenuItem v-else @select="emit('activate')">
                    Ativar
                </DropdownMenuItem>
                <DropdownMenuItem
                    variant="destructive"
                    @select="emit('delete')"
                >
                    Excluir
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

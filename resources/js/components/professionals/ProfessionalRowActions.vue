<script setup lang="ts">
import { MoreHorizontal } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export type ActionableProfessional = {
    id: string;
    display_name: string;
    status: 'active' | 'inactive';
    deleted_at: string | null;
};

withDefaults(
    defineProps<{
        professional: ActionableProfessional;
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
                :aria-label="`Ações para ${professional.display_name}`"
            >
                <MoreHorizontal class="size-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <template v-if="professional.deleted_at">
                <DropdownMenuItem @select="emit('restore')">
                    Restaurar
                </DropdownMenuItem>
            </template>
            <template v-else>
                <DropdownMenuItem @select="emit('edit')">
                    Editar
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="professional.status === 'active'"
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

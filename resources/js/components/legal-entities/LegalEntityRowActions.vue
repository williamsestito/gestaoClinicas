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
import type { LegalEntity } from '@/types/organization';

withDefaults(
    defineProps<{
        legalEntity: LegalEntity;
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const emit = defineEmits<{
    edit: [];
    toggleStatus: [];
    makePrimary: [];
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
                :aria-label="`Ações para ${legalEntity.legal_name}`"
            >
                <MoreHorizontal class="size-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <template v-if="legalEntity.deleted_at">
                <DropdownMenuItem @select="emit('restore')">
                    Restaurar
                </DropdownMenuItem>
            </template>
            <template v-else>
                <DropdownMenuItem @select="emit('edit')">
                    Editar
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="!legalEntity.is_primary"
                    @select="emit('toggleStatus')"
                >
                    {{
                        legalEntity.status === 'active' ? 'Inativar' : 'Ativar'
                    }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="
                        !legalEntity.is_primary &&
                        legalEntity.status === 'active'
                    "
                    @select="emit('makePrimary')"
                >
                    Definir como principal
                </DropdownMenuItem>
                <template v-if="!legalEntity.is_primary">
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

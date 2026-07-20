<script setup lang="ts">
import { Pencil, Power, PowerOff } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { EditableMembership } from '@/components/users/UserForm.vue';

defineProps<{
    membership: EditableMembership & { user: { is_active: boolean } };
    disabled?: boolean;
}>();

const emit = defineEmits<{
    edit: [];
    toggleStatus: [];
}>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="sm"
                :disabled="disabled"
                :aria-label="`Ações para ${membership.user.name}`"
            >
                Ações
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem @select="emit('edit')">
                <Pencil class="size-4" />
                Editar
            </DropdownMenuItem>
            <DropdownMenuItem @select="emit('toggleStatus')">
                <component
                    :is="membership.user.is_active ? PowerOff : Power"
                    class="size-4"
                />
                {{ membership.user.is_active ? 'Inativar' : 'Ativar' }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

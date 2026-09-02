<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

withDefaults(
    defineProps<{
        status: 'active' | 'inactive';
        deletedAt?: string | null;
        highlightLabel?: string;
    }>(),
    {
        deletedAt: null,
        highlightLabel: undefined,
    },
);
</script>

<template>
    <Badge v-if="highlightLabel" variant="secondary">{{
        highlightLabel
    }}</Badge>
    <Badge v-if="deletedAt" variant="destructive">Removida</Badge>
    <Badge v-else :variant="status === 'active' ? 'default' : 'destructive'">
        {{ status === 'active' ? 'Ativa' : 'Inativa' }}
    </Badge>
</template>

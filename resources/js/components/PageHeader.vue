<script setup lang="ts">
import Heading from '@/components/Heading.vue';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
    }>(),
    {
        description: undefined,
    },
);
</script>

<template>
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <Heading variant="small" :title="title" :description="description" />

        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>

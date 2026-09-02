<script setup lang="ts">
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
    <div class="flex flex-col items-center gap-2 px-4 py-8 text-center">
        <div
            v-if="$slots.icon"
            class="mb-1 flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground"
        >
            <slot name="icon" />
        </div>
        <p class="text-sm font-medium">{{ title }}</p>
        <p v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </p>
        <div v-if="$slots.action" class="mt-2">
            <slot name="action" />
        </div>
    </div>
</template>

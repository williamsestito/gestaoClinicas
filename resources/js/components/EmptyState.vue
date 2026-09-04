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
            class="bg-muted text-muted-foreground mb-1 flex size-10 items-center justify-center rounded-full"
        >
            <slot name="icon" />
        </div>
        <p class="text-sm font-medium">{{ title }}</p>
        <p v-if="description" class="text-muted-foreground text-sm">
            {{ description }}
        </p>
        <div v-if="$slots.action" class="mt-2">
            <slot name="action" />
        </div>
    </div>
</template>

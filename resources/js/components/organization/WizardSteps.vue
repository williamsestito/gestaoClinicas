<script setup lang="ts">
defineProps<{
    steps: string[];
    current: number;
}>();
</script>

<template>
    <ol class="flex flex-wrap items-center gap-2 text-sm">
        <li
            v-for="(step, index) in steps"
            :key="step"
            class="flex items-center gap-2"
            :class="
                index === current
                    ? 'text-foreground font-semibold'
                    : 'text-muted-foreground'
            "
        >
            <span
                class="flex size-6 items-center justify-center rounded-full border text-xs"
                :class="
                    index < current
                        ? 'border-primary bg-primary text-primary-foreground'
                        : index === current
                          ? 'border-primary text-primary'
                          : 'border-muted-foreground/30'
                "
            >
                {{ index + 1 }}
            </span>
            {{ step }}
            <span
                v-if="index < steps.length - 1"
                class="text-muted-foreground/50 mx-1"
                >›</span
            >
        </li>
    </ol>
</template>

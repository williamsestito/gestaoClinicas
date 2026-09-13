<script setup lang="ts">
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type BarDatum = {
    label: string;
    value: number;
};

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        data: BarDatum[];
        emptyMessage?: string;
        formatValue?: (value: number) => string;
    }>(),
    {
        description: undefined,
        emptyMessage: 'Sem dados neste período.',
        formatValue: (value: number) => String(value),
    },
);

const maxValue = computed(() =>
    Math.max(...props.data.map((item) => item.value), 1),
);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ title }}</CardTitle>
            <CardDescription v-if="description">
                {{ description }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <p v-if="data.length === 0" class="text-sm text-muted-foreground">
                {{ emptyMessage }}
            </p>
            <ul v-else class="grid gap-2">
                <li
                    v-for="item in data"
                    :key="item.label"
                    class="grid grid-cols-[4.5rem_1fr_auto] items-center gap-3 text-sm"
                >
                    <span class="truncate text-muted-foreground">
                        {{ item.label }}
                    </span>
                    <span
                        class="h-4 min-w-1 rounded-sm bg-primary"
                        :style="{
                            width: `${(item.value / maxValue) * 100}%`,
                        }"
                    />
                    <span class="tabular-nums">
                        {{ formatValue(item.value) }}
                    </span>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>

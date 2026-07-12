<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { WEEKDAYS } from '@/types/organization';
import type { OpeningHourForm } from '@/types/organization';

const hours = defineModel<OpeningHourForm[]>({ required: true });

defineProps<{
    error?: string;
}>();

function addRow() {
    hours.value = [
        ...hours.value,
        { day_of_week: 1, opens_at: '08:00', closes_at: '18:00' },
    ];
}

function removeRow(index: number) {
    hours.value = hours.value.filter((_, i) => i !== index);
}
</script>

<template>
    <div class="grid gap-3">
        <div v-if="hours.length === 0" class="text-sm text-muted-foreground">
            Nenhum horário de funcionamento cadastrado ainda.
        </div>

        <div
            v-for="(hour, index) in hours"
            :key="index"
            class="flex flex-wrap items-center gap-2"
        >
            <select
                v-model.number="hour.day_of_week"
                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option
                    v-for="day in WEEKDAYS"
                    :key="day.value"
                    :value="day.value"
                >
                    {{ day.label }}
                </option>
            </select>

            <Input v-model="hour.opens_at" type="time" class="w-32" />
            <span class="text-sm text-muted-foreground">às</span>
            <Input v-model="hour.closes_at" type="time" class="w-32" />

            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="removeRow(index)"
                >Remover</Button
            >
        </div>

        <p v-if="error" class="text-sm text-red-600 dark:text-red-500">
            {{ error }}
        </p>

        <Button type="button" variant="outline" class="w-fit" @click="addRow"
            >Adicionar horário</Button
        >
    </div>
</template>

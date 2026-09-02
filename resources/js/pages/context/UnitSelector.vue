<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { update } from '@/routes/context/unit';
import type { Unit } from '@/types/organization';

defineProps<{
    units: Unit[];
}>();

const form = useForm({ unit_id: '' });

function select(unit: Unit) {
    form.unit_id = unit.id;
    form.put(update().url);
}
</script>

<template>
    <Head title="Selecionar unidade" />

    <div class="mx-auto flex max-w-md flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Escolha uma unidade</h1>
            <p class="text-sm text-muted-foreground">
                Você tem acesso a mais de uma unidade nesta organização.
            </p>
        </div>

        <div class="grid gap-3">
            <Card
                v-for="unit in units"
                :key="unit.id"
                class="cursor-pointer transition hover:border-primary"
                @click="select(unit)"
            >
                <CardContent class="flex items-center justify-between py-4">
                    <span class="font-medium">{{ unit.name }}</span>
                    <Button size="sm" :disabled="form.processing"
                        >Selecionar</Button
                    >
                </CardContent>
            </Card>
        </div>
    </div>
</template>

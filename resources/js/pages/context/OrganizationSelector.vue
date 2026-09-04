<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { update } from '@/routes/context/organization';
import type { Organization } from '@/types/organization';

defineProps<{
    organizations: Organization[];
}>();

const form = useForm({ organization_id: '' });

function select(organization: Organization) {
    form.organization_id = organization.id;
    form.put(update().url);
}
</script>

<template>
    <Head title="Selecionar organização" />

    <div class="mx-auto flex max-w-md flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Escolha uma organização</h1>
            <p class="text-sm text-muted-foreground">
                Você tem acesso a mais de uma organização.
            </p>
        </div>

        <div class="grid gap-3">
            <Card
                v-for="organization in organizations"
                :key="organization.id"
                class="cursor-pointer transition hover:border-primary"
                @click="select(organization)"
            >
                <CardContent class="flex items-center justify-between py-4">
                    <span class="font-medium">{{ organization.name }}</span>
                    <Button size="sm" :disabled="form.processing"
                        >Selecionar</Button
                    >
                </CardContent>
            </Card>
        </div>
    </div>
</template>

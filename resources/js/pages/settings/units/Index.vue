<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { Unit } from '@/types/organization';

defineProps<{
    units: Unit[];
}>();
</script>

<template>
    <Head title="Unidades" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Unidades"
                description="Unidades da sua organização"
            />
            <Link href="/settings/units/create">
                <Button>Nova unidade</Button>
            </Link>
        </div>

        <Card v-if="units.length === 0">
            <CardContent
                class="py-10 text-center text-sm text-muted-foreground"
            >
                Nenhuma unidade cadastrada ainda.
            </CardContent>
        </Card>

        <div v-else class="grid gap-3">
            <Card v-for="unit in units" :key="unit.id">
                <CardContent class="flex items-center justify-between py-4">
                    <div>
                        <p class="font-medium">
                            {{ unit.name }}
                            <Badge
                                v-if="unit.is_headquarters"
                                variant="secondary"
                                class="ml-2"
                                >Matriz</Badge
                            >
                            <Badge
                                :variant="
                                    unit.status === 'active'
                                        ? 'default'
                                        : 'destructive'
                                "
                                class="ml-2"
                            >
                                {{
                                    unit.status === 'active'
                                        ? 'Ativa'
                                        : 'Inativa'
                                }}
                            </Badge>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ unit.code }}
                        </p>
                    </div>
                    <Link :href="`/settings/units/${unit.id}/edit`">
                        <Button variant="outline" size="sm">Editar</Button>
                    </Link>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

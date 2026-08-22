<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import patientPortal from '@/routes/patient-portal';

defineProps<{
    patients: Array<{
        id: string;
        name: string;
        birth_date: string;
        role: 'self' | 'dependent';
        role_label: string;
    }>;
}>();

defineOptions({
    layout: {
        title: 'Meus dados',
    },
});
</script>

<template>
    <Head title="Portal do paciente" />

    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-medium">Meus dados</h1>
            <Button as-child size="sm">
                <Link :href="patientPortal.dependents.create()">
                    Adicionar dependente
                </Link>
            </Button>
        </div>

        <div class="grid gap-4">
            <Card v-for="patient in patients" :key="patient.id">
                <CardContent
                    class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <Link
                        :href="patientPortal.patients.edit(patient.id)"
                        class="flex-1"
                    >
                        <p class="font-medium">{{ patient.name }}</p>
                        <p class="text-sm text-muted-foreground">
                            Nascimento: {{ patient.birth_date }}
                        </p>
                    </Link>
                    <div class="flex items-center gap-2">
                        <Badge variant="secondary">
                            {{ patient.role_label }}
                        </Badge>
                        <Button as-child variant="outline" size="sm">
                            <Link
                                :href="
                                    patientPortal.appointments.index(patient.id)
                                "
                            >
                                Agendamentos
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

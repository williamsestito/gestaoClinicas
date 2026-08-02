<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import StatusBadge from '@/components/StatusBadge.vue';
import { edit } from '@/routes/settings/professionals';
import { index as servicesIndex } from '@/routes/settings/professionals/services';
import { index as registrationsIndex } from '@/routes/settings/professionals/specialties';
import { index as unitsIndex } from '@/routes/settings/professionals/units';

const props = defineProps<{
    professional: {
        id: string;
        display_name: string;
        status: 'active' | 'inactive';
    };
    active: 'general' | 'specialties' | 'units' | 'services';
}>();

const tabs = [
    {
        key: 'general',
        label: 'Dados gerais',
        href: () => edit(props.professional.id).url,
    },
    {
        key: 'specialties',
        label: 'Especialidades e registros',
        href: () => registrationsIndex(props.professional.id).url,
    },
    {
        key: 'units',
        label: 'Unidades de atuação',
        href: () => unitsIndex(props.professional.id).url,
    },
    {
        key: 'services',
        label: 'Serviços executados',
        href: () => servicesIndex(props.professional.id).url,
    },
] as const;
</script>

<template>
    <div class="flex flex-col gap-3 border-b pb-4">
        <div class="flex items-center gap-3">
            <h2 class="text-lg font-semibold">
                {{ professional.display_name }}
            </h2>
            <StatusBadge :status="professional.status" />
        </div>

        <nav aria-label="Seções do profissional" class="flex flex-wrap gap-1">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="tab.href()"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                :class="
                    tab.key === active
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                "
                :aria-current="tab.key === active ? 'page' : undefined"
            >
                {{ tab.label }}
            </Link>
        </nav>
    </div>
</template>

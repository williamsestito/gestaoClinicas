<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { update } from '@/routes/settings/modules';

type ModuleRow = {
    key: string;
    label: string;
    enabled: boolean;
};

const props = defineProps<{
    modules: ModuleRow[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Gestão da clínica' },
            { title: 'Módulos' },
        ],
    },
});

const form = useForm({
    modules: Object.fromEntries(
        props.modules.map((module) => [module.key, module.enabled]),
    ) as Record<string, boolean>,
});

function submit() {
    form.put(update().url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Módulos" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Módulos"
            description="Habilite os módulos de especialidade que a clínica utiliza. Mais de um módulo pode ficar ativo ao mesmo tempo."
        />

        <form class="max-w-2xl space-y-4" @submit.prevent="submit">
            <div
                v-for="module in modules"
                :key="module.key"
                class="flex items-center justify-between rounded-lg border p-4"
            >
                <Label :for="`module-${module.key}`" class="font-normal">
                    {{ module.label }}
                </Label>
                <Checkbox
                    :id="`module-${module.key}`"
                    v-model:model-value="form.modules[module.key]"
                    :disabled="!canManage"
                />
            </div>

            <Button type="submit" :disabled="!canManage || form.processing">
                <Spinner v-if="form.processing" />
                Salvar
            </Button>
        </form>
    </div>
</template>

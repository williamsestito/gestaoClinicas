<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { update } from '@/routes/settings/site/sections';
import { LANDING_SECTION_LABELS } from '@/types/site';
import type { LandingSection } from '@/types/site';

const props = defineProps<{
    sections: LandingSection[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Presença digital' },
            { title: 'Site da clínica' },
            { title: 'Seções' },
        ],
    },
});

const form = useForm<{ sections: LandingSection[] }>({
    sections: props.sections.map((section) => ({ ...section })),
});

function moveUp(index: number) {
    if (index === 0) {
        return;
    }

    const items = form.sections;
    [items[index - 1], items[index]] = [items[index], items[index - 1]];
}

function moveDown(index: number) {
    if (index === form.sections.length - 1) {
        return;
    }

    const items = form.sections;
    [items[index], items[index + 1]] = [items[index + 1], items[index]];
}

function submit() {
    form.put(update().url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Seções da landing page" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Seções da landing page"
            description="Escolha a ordem e quais seções aparecem na página pública."
        />

        <div class="max-w-2xl overflow-x-auto rounded-md border">
            <table class="w-full text-sm">
                <thead
                    class="border-b bg-muted/50 text-left text-muted-foreground"
                >
                    <tr>
                        <th class="px-3 py-2 font-medium">
                            <span class="sr-only">Ordem</span>
                        </th>
                        <th class="px-3 py-2 font-medium">Seção</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 font-medium">Ativa na navbar</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(section, index) in form.sections"
                        :key="section.type"
                        class="border-b last:border-0"
                    >
                        <td class="px-3 py-1.5">
                            <div class="flex gap-0.5">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-6"
                                    :disabled="index === 0"
                                    :aria-label="`Mover ${LANDING_SECTION_LABELS[section.type]} para cima`"
                                    @click="moveUp(index)"
                                >
                                    <ArrowUp class="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-6"
                                    :disabled="
                                        index === form.sections.length - 1
                                    "
                                    :aria-label="`Mover ${LANDING_SECTION_LABELS[section.type]} para baixo`"
                                    @click="moveDown(index)"
                                >
                                    <ArrowDown class="size-3.5" />
                                </Button>
                            </div>
                        </td>
                        <td class="px-3 py-1.5 font-medium">
                            {{ LANDING_SECTION_LABELS[section.type] }}
                        </td>
                        <td class="px-3 py-1.5">
                            <Badge
                                :variant="
                                    section.active ? 'default' : 'secondary'
                                "
                            >
                                {{ section.active ? 'Ativa' : 'Inativa' }}
                            </Badge>
                        </td>
                        <td class="px-3 py-1.5">
                            <Checkbox
                                v-model:model-value="section.active"
                                :aria-label="`Ativar/inativar ${LANDING_SECTION_LABELS[section.type]}`"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Button class="w-fit" :disabled="form.processing" @click="submit">
            <Spinner v-if="form.processing" />
            Salvar ordem
        </Button>
    </div>
</template>

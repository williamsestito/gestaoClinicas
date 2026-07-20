<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
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

        <div class="grid max-w-2xl gap-3">
            <Card v-for="(section, index) in form.sections" :key="section.type">
                <CardContent
                    class="flex items-center justify-between gap-4 py-4"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex flex-col">
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
                                :disabled="index === form.sections.length - 1"
                                :aria-label="`Mover ${LANDING_SECTION_LABELS[section.type]} para baixo`"
                                @click="moveDown(index)"
                            >
                                <ArrowDown class="size-3.5" />
                            </Button>
                        </div>
                        <div>
                            <p class="font-medium">
                                {{ LANDING_SECTION_LABELS[section.type] }}
                            </p>
                            <Badge
                                :variant="
                                    section.active ? 'default' : 'secondary'
                                "
                                class="mt-1"
                            >
                                {{ section.active ? 'Ativa' : 'Inativa' }}
                            </Badge>
                        </div>
                    </div>

                    <Label class="flex items-center gap-2 font-normal">
                        <Checkbox v-model:model-value="section.active" />
                        Ativa
                    </Label>
                </CardContent>
            </Card>
        </div>

        <Button class="w-fit" :disabled="form.processing" @click="submit">
            <Spinner v-if="form.processing" />
            Salvar ordem
        </Button>
    </div>
</template>

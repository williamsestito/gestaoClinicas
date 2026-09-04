<script setup lang="ts">
import { Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    BENEFIT_ICONS,
    benefitIconFor,
    benefitIconLabel,
} from '@/lib/benefit-icons';

const model = defineModel<string | null>({ default: null });

const open = ref(false);
const search = ref('');

const filteredIcons = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (term === '') {
        return BENEFIT_ICONS;
    }

    return BENEFIT_ICONS.filter(
        (option) =>
            option.key.toLowerCase().includes(term) ||
            option.label.toLowerCase().includes(term),
    );
});

function select(key: string) {
    model.value = key;
    open.value = false;
    search.value = '';
}
</script>

<template>
    <Button
        type="button"
        variant="outline"
        class="w-fit justify-start gap-2"
        @click="open = true"
    >
        <component :is="benefitIconFor(model)" class="size-4" />
        {{ benefitIconLabel(model) }}
    </Button>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Escolher ícone</DialogTitle>
                <DialogDescription>
                    Selecione o ícone exibido junto a este benefício na página
                    pública.
                </DialogDescription>
            </DialogHeader>

            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Buscar ícone por nome"
                    aria-label="Buscar ícone por nome"
                    class="pl-8"
                    autofocus
                />
            </div>

            <div
                v-if="filteredIcons.length > 0"
                class="grid max-h-80 grid-cols-3 gap-2 overflow-y-auto sm:grid-cols-4"
            >
                <button
                    v-for="option in filteredIcons"
                    :key="option.key"
                    type="button"
                    class="flex flex-col items-center gap-1.5 rounded-md border border-transparent p-3 text-center hover:border-border hover:bg-muted"
                    :class="{ 'border-primary bg-muted': model === option.key }"
                    @click="select(option.key)"
                >
                    <component :is="option.icon" class="size-6" />
                    <span class="text-xs text-muted-foreground">{{
                        option.label
                    }}</span>
                </button>
            </div>
            <p v-else class="py-6 text-center text-sm text-muted-foreground">
                Nenhum ícone encontrado para "{{ search }}".
            </p>
        </DialogContent>
    </Dialog>
</template>

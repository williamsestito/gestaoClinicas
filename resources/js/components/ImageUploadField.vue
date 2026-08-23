<script setup lang="ts">
import { Upload } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    id: string;
    label: string;
    currentUrl?: string | null;
    helperText?: string;
}>();

const model = defineModel<File | null>({ default: null });

const inputRef = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);

function onChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }

    model.value = file;
    previewUrl.value = file ? URL.createObjectURL(file) : null;
}

function clear() {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }

    model.value = null;
    previewUrl.value = null;

    if (inputRef.value) {
        inputRef.value.value = '';
    }
}
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">{{ label }}</Label>
        <div class="flex items-center gap-3">
            <img
                v-if="previewUrl ?? props.currentUrl"
                :src="previewUrl ?? props.currentUrl ?? undefined"
                :alt="`Pré-visualização de ${label}`"
                class="size-12 shrink-0 rounded-md border border-border object-cover"
            />
            <button
                type="button"
                class="flex items-center gap-2 rounded-md border border-dashed border-input px-3 py-2 text-sm text-muted-foreground transition-colors hover:border-primary hover:text-foreground"
                @click="inputRef?.click()"
            >
                <Upload class="size-4" />
                {{
                    (previewUrl ?? props.currentUrl)
                        ? 'Trocar imagem'
                        : 'Selecionar imagem'
                }}
            </button>
            <input
                :id="id"
                ref="inputRef"
                type="file"
                accept="image/*"
                class="hidden"
                @change="onChange"
            />
            <Button
                v-if="previewUrl"
                type="button"
                variant="ghost"
                size="sm"
                @click="clear"
            >
                Remover seleção
            </Button>
        </div>
        <p v-if="helperText" class="text-sm text-muted-foreground">
            {{ helperText }}
        </p>
    </div>
</template>

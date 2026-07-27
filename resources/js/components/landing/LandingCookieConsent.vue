<script setup lang="ts">
import { X } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';

const STORAGE_KEY = 'clinic-cookie-consent';

const visible = ref(false);

onMounted(() => {
    visible.value = window.localStorage.getItem(STORAGE_KEY) === null;
});

function decide(value: 'accepted' | 'rejected') {
    window.localStorage.setItem(STORAGE_KEY, value);
    visible.value = false;
}
</script>

<template>
    <div
        v-if="visible"
        role="region"
        aria-label="Consentimento de cookies"
        class="fixed right-4 bottom-4 left-4 z-50 mx-auto flex max-w-sm flex-col gap-3 rounded-2xl border border-border bg-card p-4 shadow-lg sm:right-4 sm:left-auto"
    >
        <button
            type="button"
            aria-label="Fechar"
            class="absolute top-3 right-3 text-muted-foreground hover:text-foreground"
            @click="decide('accepted')"
        >
            <X class="size-4" />
        </button>

        <p class="pr-6 text-sm text-muted-foreground">
            Usamos cookies essenciais para o funcionamento do site. Você pode
            aceitar ou recusar o uso de cookies não essenciais.
        </p>

        <div class="flex gap-2">
            <Button size="sm" class="flex-1" @click="decide('accepted')">
                Aceitar
            </Button>
            <Button
                size="sm"
                variant="outline"
                class="flex-1"
                @click="decide('rejected')"
            >
                Recusar
            </Button>
        </div>
    </div>
</template>

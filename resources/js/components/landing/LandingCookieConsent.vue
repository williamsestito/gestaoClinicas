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
        class="border-border bg-card fixed bottom-4 left-4 right-4 z-50 mx-auto flex max-w-sm flex-col gap-3 rounded-2xl border p-4 shadow-lg sm:left-auto sm:right-4"
    >
        <button
            type="button"
            aria-label="Fechar"
            class="text-muted-foreground hover:text-foreground absolute right-3 top-3"
            @click="decide('accepted')"
        >
            <X class="size-4" />
        </button>

        <p class="text-muted-foreground pr-6 text-sm">
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

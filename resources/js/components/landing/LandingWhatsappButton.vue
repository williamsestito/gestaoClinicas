<script setup lang="ts">
import { MessageCircle } from '@lucide/vue';
import { computed } from 'vue';
import { buildWhatsAppUrl } from '@/lib/whatsapp';
import type { PublicContact } from '@/types/site';

const props = defineProps<{
    contact: PublicContact | null;
}>();

// Mensagem inicial fixa (não há campo cadastrado para isso ainda) — só o
// suficiente para a clínica saber de onde veio o contato.
const whatsappUrl = computed(() => {
    const url = buildWhatsAppUrl(props.contact?.whatsapp);

    if (!url) {
        return null;
    }

    const message = `Olá! Vim pelo site da ${props.contact?.name ?? 'clínica'} e gostaria de mais informações.`;

    return `${url}?text=${encodeURIComponent(message)}`;
});
</script>

<template>
    <a
        v-if="whatsappUrl"
        :href="whatsappUrl"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Falar no WhatsApp"
        title="Falar no WhatsApp"
        class="fixed bottom-5 left-5 z-40 flex size-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg transition-transform hover:scale-105 motion-reduce:transition-none"
    >
        <MessageCircle class="size-7" aria-hidden="true" />
    </a>
</template>

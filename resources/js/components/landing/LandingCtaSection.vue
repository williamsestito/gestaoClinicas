<script setup lang="ts">
import { MessageCircle } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { buildWhatsAppUrl } from '@/lib/whatsapp';
import type { PublicContact, PublicSiteContent } from '@/types/site';

const props = defineProps<{
    site: PublicSiteContent;
    contact: PublicContact | null;
    showScheduling: boolean;
}>();

const whatsappUrl = computed(() => buildWhatsAppUrl(props.contact?.whatsapp));
</script>

<template>
    <section
        id="cta"
        class="bg-primary text-primary-foreground scroll-mt-16 py-14"
    >
        <div
            class="mx-auto flex max-w-4xl flex-col items-center gap-4 px-4 text-center sm:px-6"
        >
            <h2 class="text-2xl font-semibold sm:text-3xl">
                Pronto para cuidar de você?
            </h2>
            <p class="text-primary-foreground/80">
                Fale com a equipe {{ site.title }} e agende sua avaliação.
            </p>

            <div class="mt-2 flex flex-col gap-3 sm:flex-row">
                <a v-if="showScheduling" href="#scheduling">
                    <Button size="lg" variant="secondary">
                        Agendar horário
                    </Button>
                </a>
                <a
                    v-if="whatsappUrl"
                    :href="whatsappUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <Button
                        size="lg"
                        variant="outline"
                        class="border-primary-foreground/40 text-primary-foreground hover:bg-primary-foreground/10 bg-transparent"
                    >
                        <MessageCircle class="size-4" />
                        Falar no WhatsApp
                    </Button>
                </a>
            </div>
        </div>
    </section>
</template>

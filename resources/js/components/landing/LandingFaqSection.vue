<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import type { PublicFaq } from '@/types/site';

defineProps<{
    faqs: PublicFaq[];
}>();
</script>

<template>
    <section
        v-if="faqs.length > 0"
        id="faq"
        class="mx-auto max-w-3xl scroll-mt-16 px-4 py-16 sm:px-6"
    >
        <div class="mx-auto mb-10 max-w-2xl text-center">
            <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                Perguntas frequentes
            </h2>
        </div>

        <div class="divide-border border-border divide-y rounded-2xl border">
            <Collapsible
                v-for="faq in faqs"
                :key="faq.id"
                v-slot="{ open }"
                as="div"
                class="px-5"
            >
                <CollapsibleTrigger
                    class="focus-visible:ring-ring/50 flex w-full items-center justify-between gap-4 rounded-sm py-4 text-left font-medium outline-none focus-visible:ring-[3px]"
                >
                    {{ faq.question }}
                    <ChevronDown
                        class="text-muted-foreground size-4 shrink-0 transition-transform"
                        :class="{ 'rotate-180': open }"
                    />
                </CollapsibleTrigger>
                <CollapsibleContent
                    class="text-muted-foreground whitespace-pre-line pb-4 text-sm"
                >
                    {{ faq.answer }}
                </CollapsibleContent>
            </Collapsible>
        </div>
    </section>
</template>

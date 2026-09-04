<script setup lang="ts">
import { Link2, User, X } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { useLandingScheduling } from '@/composables/useLandingScheduling';
import type { PublicProfessional } from '@/types/site';

defineProps<{
    professionals: PublicProfessional[];
}>();

const { selectedProfessionalId, selectedProfessionalName } =
    useLandingScheduling();

// `professional_id` vem nulo para uma ficha puramente promocional
// (SiteProfessional sem vínculo com o cadastro operacional) — nesse caso
// não há profissional real para registrar na solicitação, só o texto nas
// observações mesmo.
function selectProfessional(professional: PublicProfessional) {
    selectedProfessionalId.value = professional.professional_id;
    selectedProfessionalName.value = professional.name;
}

const expandedProfessional = ref<PublicProfessional | null>(null);
</script>

<template>
    <section
        v-if="professionals.length > 0"
        id="professionals"
        class="mx-auto max-w-6xl scroll-mt-16 px-4 py-16 sm:px-6"
    >
        <div class="mx-auto mb-10 max-w-2xl text-center">
            <p class="landing-eyebrow mb-2">Quem cuida de você</p>
            <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                Nossa equipe
            </h2>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="professional in professionals"
                :key="professional.id"
                class="flex flex-col items-center rounded-(--landing-radius-md) border border-border bg-card p-6 text-center shadow-sm"
            >
                <button
                    v-if="professional.photo_url"
                    type="button"
                    class="rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :aria-label="`Ver foto de ${professional.name} em tamanho maior`"
                    @click="expandedProfessional = professional"
                >
                    <img
                        :src="professional.photo_url"
                        :alt="professional.name"
                        loading="lazy"
                        class="size-24 rounded-full object-cover"
                    />
                </button>
                <div
                    v-else
                    class="flex size-24 items-center justify-center rounded-full bg-muted text-muted-foreground"
                >
                    <User class="size-10" />
                </div>

                <h3 class="mt-4 font-semibold">{{ professional.name }}</h3>
                <p v-if="professional.role_title" class="text-sm text-primary">
                    {{ professional.role_title }}
                </p>
                <p
                    v-if="professional.specialty"
                    class="text-sm text-muted-foreground"
                >
                    {{ professional.specialty }}
                </p>
                <p
                    v-if="professional.professional_register"
                    class="text-xs text-muted-foreground"
                >
                    {{ professional.professional_register }}
                </p>
                <p
                    v-if="professional.bio"
                    class="mt-2 text-sm text-muted-foreground"
                >
                    {{ professional.bio }}
                </p>

                <div
                    v-if="
                        professional.facebook_url ||
                        professional.instagram_url ||
                        professional.linkedin_url
                    "
                    class="mt-3 flex justify-center gap-3 text-muted-foreground"
                >
                    <a
                        v-if="professional.facebook_url"
                        :href="professional.facebook_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        :aria-label="`Facebook de ${professional.name}`"
                        class="hover:text-foreground"
                    >
                        <Link2 class="size-4" />
                    </a>
                    <a
                        v-if="professional.instagram_url"
                        :href="professional.instagram_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        :aria-label="`Instagram de ${professional.name}`"
                        class="hover:text-foreground"
                    >
                        <Link2 class="size-4" />
                    </a>
                    <a
                        v-if="professional.linkedin_url"
                        :href="professional.linkedin_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        :aria-label="`LinkedIn de ${professional.name}`"
                        class="hover:text-foreground"
                    >
                        <Link2 class="size-4" />
                    </a>
                </div>

                <a
                    href="#scheduling"
                    class="mt-4 w-full"
                    @click="selectProfessional(professional)"
                >
                    <Button class="w-full rounded-full" variant="outline">
                        Agendar
                    </Button>
                </a>
            </div>
        </div>

        <Dialog
            :open="expandedProfessional !== null"
            @update:open="(open) => !open && (expandedProfessional = null)"
        >
            <DialogContent
                class="max-w-2xl gap-0 border-0 bg-transparent p-0 shadow-none"
            >
                <DialogTitle class="sr-only">
                    {{ expandedProfessional?.name }}
                </DialogTitle>
                <div class="relative">
                    <img
                        v-if="expandedProfessional"
                        :src="expandedProfessional.photo_url ?? undefined"
                        :alt="expandedProfessional.name"
                        class="max-h-[80vh] w-full rounded-lg object-contain"
                    />
                    <DialogClose
                        class="absolute top-2 right-2 rounded-full bg-black/60 p-2 text-white hover:bg-black/80"
                        aria-label="Fechar"
                    >
                        <X class="size-4" />
                    </DialogClose>
                </div>
            </DialogContent>
        </Dialog>
    </section>
</template>

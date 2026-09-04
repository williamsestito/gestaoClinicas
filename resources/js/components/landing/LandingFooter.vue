<script setup lang="ts">
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { LANDING_NAV_LABELS } from '@/lib/landing-nav';
import type {
    LandingSectionType,
    PublicContact,
    PublicSiteContent,
} from '@/types/site';

const props = defineProps<{
    site: PublicSiteContent;
    contact: PublicContact | null;
    activeTypes?: LandingSectionType[];
}>();

const navLinks = computed(() =>
    (props.activeTypes ?? [])
        .filter(
            (type): type is keyof typeof LANDING_NAV_LABELS =>
                type in LANDING_NAV_LABELS,
        )
        .map((type) => ({ type, label: LANDING_NAV_LABELS[type]! })),
);

const socialLinks = computed(() =>
    [
        { label: 'Facebook', url: props.site.facebook_url },
        { label: 'Instagram', url: props.site.instagram_url },
        { label: 'LinkedIn', url: props.site.linkedin_url },
    ].filter((link): link is { label: string; url: string } => !!link.url),
);

const currentYear = new Date().getFullYear();
</script>

<template>
    <footer class="border-border bg-muted/30 border-t">
        <div
            class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-4"
        >
            <div class="space-y-3">
                <div class="flex items-center gap-2 font-semibold">
                    <img
                        v-if="site.logo_url"
                        :src="site.logo_url"
                        :alt="site.title"
                        class="size-8 rounded-md object-cover"
                    />
                    <AppLogoIcon
                        v-else
                        class="text-primary size-8 rounded-md fill-current"
                        aria-hidden="true"
                    />
                    <span>{{ site.title }}</span>
                </div>
                <p
                    v-if="site.description"
                    class="text-muted-foreground text-sm"
                >
                    {{ site.description }}
                </p>

                <nav
                    v-if="socialLinks.length > 0"
                    aria-label="Redes sociais"
                    class="flex gap-3 pt-1 text-sm"
                >
                    <a
                        v-for="link in socialLinks"
                        :key="link.label"
                        :href="link.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-muted-foreground hover:text-foreground underline-offset-4 hover:underline"
                    >
                        {{ link.label }}
                    </a>
                </nav>
            </div>

            <nav
                v-if="navLinks.length > 0"
                aria-label="Navegação do rodapé"
                class="space-y-2 text-sm"
            >
                <p class="text-foreground font-medium">Navegação</p>
                <ul class="space-y-1">
                    <li v-for="link in navLinks" :key="link.type">
                        <a
                            :href="`#${link.type}`"
                            class="text-muted-foreground hover:text-foreground"
                        >
                            {{ link.label }}
                        </a>
                    </li>
                </ul>
            </nav>

            <div v-if="contact" class="text-muted-foreground space-y-2 text-sm">
                <p class="text-foreground font-medium">Contato</p>
                <p v-if="contact.phone">{{ contact.phone }}</p>
                <p v-if="contact.email">{{ contact.email }}</p>
                <p v-if="contact.address">
                    {{ contact.address.street }}, {{ contact.address.number }} —
                    {{ contact.address.city }}/{{ contact.address.state }}
                </p>
            </div>

            <div class="text-muted-foreground space-y-2 text-sm">
                <p class="text-foreground font-medium">Legal</p>
                <p>© {{ currentYear }} {{ site.title }}</p>
                <p class="text-xs">
                    Site gerenciado via
                    <span class="text-foreground font-medium"
                        >Gestão de Clínicas</span
                    >
                </p>
            </div>
        </div>

        <div
            class="border-border text-muted-foreground border-t px-4 py-4 text-center text-xs sm:px-6"
        >
            {{ site.footer_text || `© ${currentYear} ${site.title}` }}
        </div>
    </footer>
</template>

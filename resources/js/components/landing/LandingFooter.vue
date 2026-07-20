<script setup lang="ts">
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import type { PublicContact, PublicSiteContent } from '@/types/site';

const props = defineProps<{
    site: PublicSiteContent;
    contact: PublicContact | null;
}>();

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
    <footer class="border-t border-border bg-muted/30">
        <div
            class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-3"
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
                        class="size-8 rounded-md fill-current text-primary"
                        aria-hidden="true"
                    />
                    <span>{{ site.title }}</span>
                </div>
                <p
                    v-if="site.description"
                    class="text-sm text-muted-foreground"
                >
                    {{ site.description }}
                </p>
            </div>

            <div v-if="contact" class="space-y-2 text-sm text-muted-foreground">
                <p class="font-medium text-foreground">Contato</p>
                <p v-if="contact.phone">{{ contact.phone }}</p>
                <p v-if="contact.email">{{ contact.email }}</p>
                <p v-if="contact.address">
                    {{ contact.address.street }}, {{ contact.address.number }} —
                    {{ contact.address.city }}/{{ contact.address.state }}
                </p>
            </div>

            <nav
                v-if="socialLinks.length > 0"
                aria-label="Redes sociais"
                class="space-y-2 text-sm"
            >
                <p class="font-medium">Redes sociais</p>
                <ul class="space-y-1">
                    <li v-for="link in socialLinks" :key="link.label">
                        <a
                            :href="link.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                        >
                            {{ link.label }}
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

        <div
            class="border-t border-border px-4 py-4 text-center text-xs text-muted-foreground sm:px-6"
        >
            {{ site.footer_text || `© ${currentYear} ${site.title}` }}
        </div>
    </footer>
</template>

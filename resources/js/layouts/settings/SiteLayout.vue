<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editSite } from '@/routes/settings/site';
import { index as indexAppointmentRequests } from '@/routes/settings/site/appointment-requests';
import { index as indexBenefits } from '@/routes/settings/site/benefits';
import { index as indexFaq } from '@/routes/settings/site/faq';
import { index as indexGallery } from '@/routes/settings/site/gallery';
import { index as indexPartners } from '@/routes/settings/site/partners';
import { index as indexProfessionals } from '@/routes/settings/site/professionals';
import { edit as editSections } from '@/routes/settings/site/sections';
import { index as indexServices } from '@/routes/settings/site/services';
import { index as indexTestimonials } from '@/routes/settings/site/testimonials';
import type { NavItem } from '@/types';

const siteNavItems: NavItem[] = [
    { title: 'Conteúdo geral', href: editSite() },
    { title: 'Seções', href: editSections() },
    { title: 'Benefícios', href: indexBenefits() },
    { title: 'Serviços', href: indexServices() },
    { title: 'Equipe', href: indexProfessionals() },
    { title: 'Galeria', href: indexGallery() },
    { title: 'Depoimentos', href: indexTestimonials() },
    { title: 'Convênios', href: indexPartners() },
    { title: 'FAQ', href: indexFaq() },
    { title: 'Agendamentos', href: indexAppointmentRequests() },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div>
        <nav
            class="mb-6 flex flex-wrap gap-1 border-b pb-3"
            aria-label="Site da clínica"
        >
            <Button
                v-for="item in siteNavItems"
                :key="toUrl(item.href)"
                variant="ghost"
                size="sm"
                :class="{ 'bg-muted': isCurrentOrParentUrl(item.href) }"
                as-child
            >
                <Link :href="item.href">{{ item.title }}</Link>
            </Button>
        </nav>

        <slot />
    </div>
</template>

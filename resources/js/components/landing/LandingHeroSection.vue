<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import type { PublicSiteContent } from '@/types/site';

const props = defineProps<{
    site: PublicSiteContent;
}>();

const page = usePage();

const brandStyle = {
    '--brand-primary': props.site.primary_color ?? 'var(--primary)',
    '--brand-secondary': props.site.secondary_color ?? 'var(--primary)',
};
</script>

<template>
    <section
        id="hero"
        class="relative overflow-hidden py-16 sm:py-24"
        :style="brandStyle"
    >
        <div
            class="pointer-events-none absolute inset-0 -z-10"
            aria-hidden="true"
        >
            <div
                class="absolute -top-24 -left-24 size-96 rounded-full opacity-20 blur-3xl"
                style="background-color: var(--brand-primary)"
            />
            <div
                class="absolute -right-24 -bottom-24 size-96 rounded-full opacity-20 blur-3xl"
                style="background-color: var(--brand-secondary)"
            />
        </div>

        <div
            class="mx-auto grid max-w-6xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2"
        >
            <div class="space-y-6 text-center lg:text-left">
                <h1
                    class="text-4xl font-bold tracking-tight text-balance sm:text-5xl"
                >
                    {{ site.title }}
                </h1>
                <p
                    v-if="site.description"
                    class="text-lg text-balance text-muted-foreground"
                >
                    {{ site.description }}
                </p>

                <div
                    class="flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start"
                >
                    <a
                        v-if="site.cta_text && site.cta_url"
                        :href="site.cta_url"
                    >
                        <Button
                            size="lg"
                            :style="{
                                backgroundColor: 'var(--brand-primary)',
                                color: '#fff',
                            }"
                        >
                            {{ site.cta_text }}
                        </Button>
                    </a>
                    <a
                        v-if="site.cta_secondary_text && site.cta_secondary_url"
                        :href="site.cta_secondary_url"
                    >
                        <Button size="lg" variant="outline">
                            {{ site.cta_secondary_text }}
                        </Button>
                    </a>
                </div>

                <div
                    v-if="!site.cta_text"
                    class="flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start"
                >
                    <Link v-if="page.props.auth.user" :href="dashboard()">
                        <Button size="lg">Acessar dashboard</Button>
                    </Link>
                    <template v-else>
                        <Link :href="login()">
                            <Button size="lg">Entrar</Button>
                        </Link>
                        <Link :href="register()">
                            <Button size="lg" variant="outline"
                                >Criar conta</Button
                            >
                        </Link>
                    </template>
                </div>
            </div>

            <div v-if="site.hero_image_url" class="relative">
                <img
                    :src="site.hero_image_url"
                    :alt="site.title"
                    fetchpriority="high"
                    class="aspect-4/3 w-full rounded-2xl border border-border object-cover shadow-lg"
                />
            </div>
        </div>
    </section>
</template>

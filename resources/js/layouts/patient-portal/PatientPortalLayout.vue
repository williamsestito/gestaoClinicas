<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LogOut } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import patientPortal from '@/routes/patient-portal';
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background">
        <header class="border-b">
            <div
                class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-3"
            >
                <Link
                    :href="patientPortal.dashboard()"
                    class="flex items-center gap-2"
                >
                    <AppLogo />
                </Link>

                <nav class="flex items-center gap-2">
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="patientPortal.dashboard()">
                            Meus dados
                        </Link>
                    </Button>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="patientPortal.dependents.create()">
                            Dependentes
                        </Link>
                    </Button>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="patientPortal.logout()" as="button">
                            <LogOut class="mr-2 h-4 w-4" />
                            Sair
                        </Link>
                    </Button>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
            <slot />
        </main>
    </div>
</template>

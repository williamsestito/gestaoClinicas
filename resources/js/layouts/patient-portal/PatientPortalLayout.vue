<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LogOut, UserRound } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import patientPortal from '@/routes/patient-portal';

// "Meus dados" vai direto para o próprio cadastro (papel self) quando a
// conta tiver um — sem isso, uma conta que só gerencia a si mesma ainda
// precisava passar pela home antes de editar. Sem papel self (conta que
// só gerencia dependentes), volta para a home.
const ownPatientId = computed(
    () => usePage().props.patientPortal?.ownPatientId ?? null,
);
const myDataHref = computed(() =>
    ownPatientId.value
        ? patientPortal.patients.edit(ownPatientId.value)
        : patientPortal.dashboard(),
);
</script>

<template>
    <div class="bg-muted/30 flex min-h-svh flex-col">
        <header class="bg-background border-b">
            <div
                class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4"
            >
                <Link
                    :href="patientPortal.dashboard()"
                    class="flex items-center gap-2"
                >
                    <AppLogo />
                    <span
                        class="text-muted-foreground hidden text-sm font-medium sm:inline"
                    >
                        Portal do paciente
                    </span>
                </Link>

                <nav class="flex items-center gap-1">
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="patientPortal.dashboard()">Início</Link>
                    </Button>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="myDataHref">
                            <UserRound class="mr-2 size-4" />
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
                            <LogOut class="mr-2 size-4" />
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

<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { destroy } from '@/routes/context/organization';

/**
 * Banner fixo, sempre visível, enquanto o platform admin está "atuando
 * como" administrador de uma organização específica (ver
 * App\Actions\Organization\SetActiveOrganizationAction) — deixa claro que o
 * acesso é excepcional e dá um jeito óbvio de sair, sem se confundir com um
 * admin real da clínica. `tenant` já é compartilhado em toda página via
 * Inertia (ver TenantContextPresenter), então isto não depende de nenhum
 * prop novo por página.
 */
const page = usePage();
const tenant = computed(() => page.props.tenant);

const visible = computed(
    () =>
        Boolean(tenant.value?.isPlatformAdmin) &&
        Boolean(tenant.value?.organization),
);

function leaveManagementMode() {
    router.delete(destroy().url);
}
</script>

<template>
    <div
        v-if="visible"
        class="sticky top-0 z-50 flex flex-wrap items-center justify-between gap-2 border-b border-amber-300 bg-amber-100 px-4 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
    >
        <span>
            Você está gerenciando
            <strong>{{ tenant?.organization?.name }}</strong>
            como Superadmin.
        </span>
        <Button
            variant="outline"
            size="sm"
            class="border-amber-400 bg-transparent hover:bg-amber-200 dark:border-amber-700 dark:hover:bg-amber-900"
            @click="leaveManagementMode"
        >
            Sair do modo de gestão
        </Button>
    </div>
</template>

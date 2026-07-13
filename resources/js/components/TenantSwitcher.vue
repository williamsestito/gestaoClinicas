<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit as editOrganization } from '@/routes/context/organization';
import { edit as editUnit } from '@/routes/context/unit';

const page = usePage();
const tenant = computed(() => page.props.tenant);
</script>

<template>
    <div v-if="tenant?.organization" class="flex items-center gap-2 text-sm">
        <div class="flex items-center gap-1.5">
            <span class="font-medium">{{ tenant.organization.name }}</span>
            <Link
                v-if="tenant.availableOrganizations.length > 1"
                :href="editOrganization()"
            >
                <Button variant="ghost" size="sm" class="h-6 px-1.5 text-xs"
                    >Trocar</Button
                >
            </Link>
        </div>

        <template v-if="tenant.unit">
            <span class="text-muted-foreground">/</span>
            <div class="flex items-center gap-1.5">
                <span>{{ tenant.unit.name }}</span>
                <Badge v-if="tenant.unit.is_headquarters" variant="secondary"
                    >Matriz</Badge
                >
                <Link
                    v-if="tenant.availableUnits.length > 1"
                    :href="editUnit()"
                >
                    <Button variant="ghost" size="sm" class="h-6 px-1.5 text-xs"
                        >Trocar</Button
                    >
                </Link>
            </div>
        </template>
    </div>
</template>

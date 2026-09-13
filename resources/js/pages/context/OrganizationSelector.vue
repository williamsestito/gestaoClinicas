<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { update } from '@/routes/context/organization';
import type { Organization } from '@/types/organization';

defineProps<{
    organizations: Organization[];
}>();

const page = usePage();
// Mesma tela serve dois casos: um usuário com vínculo em várias
// organizações "trocando" entre elas, e o platform admin "gerenciando"
// qualquer organização ativa (acesso excepcional, ver
// App\Actions\Organization\SetActiveOrganizationAction e
// PlatformAdminBanner.vue) — o rótulo do botão deixa esse segundo caso
// claro, em vez de parecer só mais uma troca de contexto qualquer.
const isPlatformAdmin = computed(() => page.props.tenant?.isPlatformAdmin);

const form = useForm({ organization_id: '' });

function select(organization: Organization) {
    form.organization_id = organization.id;
    form.put(update().url);
}
</script>

<template>
    <Head title="Selecionar organização" />

    <div class="mx-auto flex max-w-md flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">
                {{
                    isPlatformAdmin
                        ? 'Escolha uma organização para gerenciar'
                        : 'Escolha uma organização'
                }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{
                    isPlatformAdmin
                        ? 'Como superadmin, você pode gerenciar qualquer organização ativa da plataforma.'
                        : 'Você tem acesso a mais de uma organização.'
                }}
            </p>
        </div>

        <div class="grid gap-3">
            <Card
                v-for="organization in organizations"
                :key="organization.id"
                class="cursor-pointer transition hover:border-primary"
                @click="select(organization)"
            >
                <CardContent class="flex items-center justify-between py-4">
                    <span class="font-medium">{{ organization.name }}</span>
                    <Button size="sm" :disabled="form.processing">
                        {{
                            isPlatformAdmin
                                ? 'Gerenciar esta organização'
                                : 'Selecionar'
                        }}
                    </Button>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

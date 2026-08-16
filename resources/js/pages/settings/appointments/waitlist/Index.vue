<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PatientSearchSelect from '@/components/appointments/PatientSearchSelect.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDateBr, formatDateTimeBr } from '@/lib/masks';
import { dashboard } from '@/routes';
import { create as createAppointment, index } from '@/routes/settings/appointments';
import { cancel, store } from '@/routes/settings/appointments/waitlist';

type Option = { id: string; name?: string; display_name?: string };

type WaitlistRow = {
    id: string;
    unit_name: string;
    professional_name: string | null;
    service_id: string;
    service_name: string;
    patient_name: string;
    preferred_date: string | null;
    notes: string | null;
    created_at: string;
};

const props = defineProps<{
    entries: WaitlistRow[];
    units: Option[];
    professionals: Option[];
    services: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Agenda', href: index() },
            { title: 'Lista de espera' },
        ],
    },
});

const form = useForm({
    unit_id: '',
    professional_id: '',
    service_id: '',
    patient_id: '',
    preferred_date: '',
    notes: '',
});

function submit() {
    form.post(store().url, { preserveScroll: true, onSuccess: () => form.reset() });
}

function cancelEntry(entryId: string) {
    router.patch(cancel(entryId).url, {}, { preserveScroll: true });
}

function scheduleUrl(entry: WaitlistRow): string {
    const params = new URLSearchParams({ waitlist_entry_id: entry.id });

    return `${createAppointment().url}?${params.toString()}`;
}

function formatDate(value: string): string {
    return formatDateTimeBr(value);
}

function formatPreferredDate(value: string | null): string | null {
    return value ? formatDateBr(value) : null;
}
</script>

<template>
    <Head title="Lista de espera" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Lista de espera"
            description="Pacientes aguardando um horário. Sem notificação automática nesta etapa — a recepção consulta e agenda manualmente quando um horário abre."
        />

        <EmptyState
            v-if="entries.length === 0"
            title="Ninguém na lista de espera."
            description="Adicione um paciente abaixo quando não houver horário disponível."
        />

        <div v-else class="grid gap-3">
            <Card v-for="entry in entries" :key="entry.id">
                <CardContent
                    class="flex flex-col gap-3 py-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="space-y-1">
                        <p class="font-medium">{{ entry.patient_name }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ entry.service_name }}
                            <template v-if="entry.professional_name">
                                — {{ entry.professional_name }}</template
                            >
                            — {{ entry.unit_name }}
                        </p>
                        <p
                            v-if="formatPreferredDate(entry.preferred_date)"
                            class="text-sm text-muted-foreground"
                        >
                            Preferência:
                            {{ formatPreferredDate(entry.preferred_date) }}
                        </p>
                        <p
                            v-if="entry.notes"
                            class="text-sm text-muted-foreground"
                        >
                            "{{ entry.notes }}"
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Adicionado em {{ formatDate(entry.created_at) }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2 sm:items-end">
                        <Link :href="scheduleUrl(entry)">
                            <Button size="sm" class="w-full sm:w-40">
                                Agendar
                            </Button>
                        </Link>
                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full sm:w-40"
                            @click="cancelEntry(entry.id)"
                        >
                            Remover
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <form
            class="grid max-w-2xl gap-4 rounded-lg border p-4"
            @submit.prevent="submit"
        >
            <h3 class="text-sm font-medium">
                Adicionar paciente à lista de espera
            </h3>

            <div class="grid gap-2">
                <Label>Paciente</Label>
                <PatientSearchSelect
                    v-model="form.patient_id"
                    :error="form.errors.patient_id"
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="waitlist-unit">Unidade</Label>
                    <select
                        id="waitlist-unit"
                        v-model="form.unit_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="" disabled>Selecione</option>
                        <option v-for="unit in units" :key="unit.id" :value="unit.id">
                            {{ unit.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.unit_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="waitlist-service">Serviço</Label>
                    <select
                        id="waitlist-service"
                        v-model="form.service_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="" disabled>Selecione</option>
                        <option
                            v-for="service in services"
                            :key="service.id"
                            :value="service.id"
                        >
                            {{ service.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.service_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="waitlist-professional">
                        Profissional (opcional — qualquer um disponível)
                    </Label>
                    <select
                        id="waitlist-professional"
                        v-model="form.professional_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">Qualquer profissional</option>
                        <option
                            v-for="professional in professionals"
                            :key="professional.id"
                            :value="professional.id"
                        >
                            {{ professional.display_name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="waitlist-preferred-date">
                        Data de preferência (opcional)
                    </Label>
                    <Input
                        id="waitlist-preferred-date"
                        v-model="form.preferred_date"
                        type="date"
                    />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="waitlist-notes">Observações (opcional)</Label>
                <Textarea id="waitlist-notes" v-model="form.notes" rows="3" />
                <InputError :message="form.errors.notes" />
            </div>

            <Button
                type="submit"
                class="w-fit"
                :disabled="form.processing || !form.patient_id"
            >
                Adicionar à lista de espera
            </Button>
        </form>
    </div>
</template>

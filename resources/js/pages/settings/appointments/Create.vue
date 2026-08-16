<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import PatientSearchSelect from '@/components/appointments/PatientSearchSelect.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import { availableSlots, index, store } from '@/routes/settings/appointments';

type Option = { id: string; name?: string; display_name?: string };
type Slot = { time: string; duration_minutes: number };

defineProps<{
    units: Option[];
    professionals: Option[];
    services: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Agenda', href: index() },
            { title: 'Novo agendamento' },
        ],
    },
});

const form = useForm({
    unit_id: '',
    professional_id: '',
    patient_id: '',
    service_id: '',
    starts_at: '',
    notes: '',
});

const date = ref('');
const slots = ref<Slot[]>([]);
const loadingSlots = ref(false);

async function loadSlots() {
    if (
        !form.unit_id ||
        !form.professional_id ||
        !form.service_id ||
        !date.value
    ) {
        slots.value = [];

        return;
    }

    loadingSlots.value = true;
    form.starts_at = '';

    try {
        const params = new URLSearchParams({
            unit_id: form.unit_id,
            professional_id: form.professional_id,
            service_id: form.service_id,
            date: date.value,
        });
        const response = await fetch(
            `${availableSlots().url}?${params.toString()}`,
            { headers: { Accept: 'application/json' } },
        );

        slots.value = response.ok
            ? ((await response.json()) as { slots: Slot[] }).slots
            : [];
    } finally {
        loadingSlots.value = false;
    }
}

watch(
    [
        () => form.unit_id,
        () => form.professional_id,
        () => form.service_id,
        date,
    ],
    loadSlots,
);

function selectSlot(time: string) {
    form.starts_at = `${date.value}T${time}:00`;
}

function cancel() {
    router.get(index().url);
}

function submit() {
    form.post(store().url, { onSuccess: () => router.get(index().url) });
}
</script>

<template>
    <Head title="Novo agendamento" />

    <div class="flex flex-col space-y-6">
        <PageHeader
            title="Novo agendamento"
            description="Crie um agendamento real para um paciente já cadastrado."
        />

        <form class="flex max-w-2xl flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label>Paciente</Label>
                <PatientSearchSelect
                    v-model="form.patient_id"
                    :error="form.errors.patient_id"
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="appointment-unit">Unidade</Label>
                    <select
                        id="appointment-unit"
                        v-model="form.unit_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="" disabled>Selecione</option>
                        <option
                            v-for="unit in units"
                            :key="unit.id"
                            :value="unit.id"
                        >
                            {{ unit.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.unit_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="appointment-professional">Profissional</Label>
                    <select
                        id="appointment-professional"
                        v-model="form.professional_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="" disabled>Selecione</option>
                        <option
                            v-for="professional in professionals"
                            :key="professional.id"
                            :value="professional.id"
                        >
                            {{ professional.display_name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.professional_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="appointment-service">Serviço</Label>
                    <select
                        id="appointment-service"
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
                    <Label for="appointment-date">Data</Label>
                    <Input id="appointment-date" v-model="date" type="date" />
                </div>
            </div>

            <div v-if="date" class="grid gap-2">
                <Label>Horários disponíveis</Label>
                <p v-if="loadingSlots" class="text-sm text-muted-foreground">
                    Buscando horários…
                </p>
                <p
                    v-else-if="slots.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Nenhum horário livre encontrado para os filtros
                    selecionados.
                </p>
                <div v-else class="flex flex-wrap gap-2">
                    <Button
                        v-for="slot in slots"
                        :key="slot.time"
                        type="button"
                        :variant="
                            form.starts_at === `${date}T${slot.time}:00`
                                ? 'default'
                                : 'outline'
                        "
                        size="sm"
                        @click="selectSlot(slot.time)"
                    >
                        {{ slot.time }}
                    </Button>
                </div>
                <InputError :message="form.errors.starts_at" />
            </div>

            <div class="grid gap-2">
                <Label for="appointment-notes">Observações (opcional)</Label>
                <Textarea id="appointment-notes" v-model="form.notes" rows="3" />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="flex items-center justify-end gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    :disabled="form.processing"
                    @click="cancel"
                >
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    :disabled="
                        form.processing || !form.starts_at || !form.patient_id
                    "
                >
                    Criar agendamento
                </Button>
            </div>
        </form>
    </div>
</template>

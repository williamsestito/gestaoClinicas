<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SlotPicker from '@/components/appointments/SlotPicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import patientPortal from '@/routes/patient-portal';

type Option = { id: string; name?: string; display_name?: string };

const props = defineProps<{
    patient: { id: string; name: string };
    units: Option[];
    professionals: Option[];
    services: Option[];
}>();

defineOptions({
    layout: {
        title: 'Novo agendamento',
    },
});

const form = useForm({
    unit_id: '',
    professional_id: '',
    service_id: '',
    starts_at: '',
    notes: '',
});

const date = ref('');

const slotsBaseUrl = computed(() => {
    if (!form.unit_id || !form.professional_id || !form.service_id) {
        return null;
    }

    const params = new URLSearchParams({
        unit_id: form.unit_id,
        professional_id: form.professional_id,
        service_id: form.service_id,
    });

    return `${patientPortal.appointments.availableSlots().url}?${params.toString()}`;
});

function cancel() {
    router.get(patientPortal.appointments.index(props.patient.id).url);
}

function submit() {
    form.post(patientPortal.appointments.store(props.patient.id).url);
}
</script>

<template>
    <Head title="Novo agendamento" />

    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-xl font-medium">Novo agendamento</h1>
            <p class="text-sm text-muted-foreground">
                Para {{ patient.name }}. A clínica confirma o horário antes de
                ele valer como marcado.
            </p>
        </div>

        <form class="flex max-w-2xl flex-col gap-6" @submit.prevent="submit">
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
            </div>

            <SlotPicker
                v-model:date="date"
                v-model:starts-at="form.starts_at"
                :base-url="slotsBaseUrl"
                :error="form.errors.starts_at"
            />

            <div class="grid gap-2">
                <Label for="appointment-notes">Observações (opcional)</Label>
                <Textarea
                    id="appointment-notes"
                    v-model="form.notes"
                    rows="3"
                />
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
                    :disabled="form.processing || !form.starts_at"
                >
                    Solicitar agendamento
                </Button>
            </div>
        </form>
    </div>
</template>

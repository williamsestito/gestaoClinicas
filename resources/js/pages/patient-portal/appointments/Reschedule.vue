<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SlotPicker from '@/components/appointments/SlotPicker.vue';
import { Button } from '@/components/ui/button';
import patientPortal from '@/routes/patient-portal';

type AppointmentInfo = {
    id: string;
    starts_at: string;
    unit_id: string;
    professional_id: string;
    service_id: string;
    professional_name: string;
    service_name: string;
    duration_minutes: number;
};

const props = defineProps<{
    patient: { id: string };
    appointment: AppointmentInfo;
}>();

defineOptions({
    layout: {
        title: 'Reagendar',
    },
});

const form = useForm({
    starts_at: '',
});

const date = ref(props.appointment.starts_at.slice(0, 10));

const slotsBaseUrl = computed(() => {
    const params = new URLSearchParams({
        unit_id: props.appointment.unit_id,
        professional_id: props.appointment.professional_id,
        service_id: props.appointment.service_id,
    });

    return `${patientPortal.appointments.availableSlots().url}?${params.toString()}`;
});

function cancel() {
    router.get(patientPortal.appointments.index(props.patient.id).url);
}

function submit() {
    form.put(
        patientPortal.appointments.reschedule.update([
            props.patient.id,
            props.appointment.id,
        ]).url,
    );
}
</script>

<template>
    <Head title="Reagendar" />

    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-xl font-medium">Reagendar</h1>
            <p class="text-sm text-muted-foreground">
                {{ appointment.service_name }} com
                {{ appointment.professional_name }}.
            </p>
        </div>

        <form class="flex max-w-2xl flex-col gap-6" @submit.prevent="submit">
            <SlotPicker
                v-model:date="date"
                v-model:starts-at="form.starts_at"
                :base-url="slotsBaseUrl"
                :error="form.errors.starts_at"
            />

            <div class="flex items-center justify-end gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    :disabled="form.processing"
                    @click="cancel"
                >
                    Voltar
                </Button>
                <Button
                    type="submit"
                    :disabled="form.processing || !form.starts_at"
                >
                    Confirmar novo horário
                </Button>
            </div>
        </form>
    </div>
</template>

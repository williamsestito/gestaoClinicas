<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SlotPicker from '@/components/appointments/SlotPicker.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { availableSlots, index } from '@/routes/settings/appointments';
import { update as updatePropose } from '@/routes/settings/appointments/propose';

type AppointmentInfo = {
    id: string;
    unit_id: string;
    professional_id: string;
    service_id: string;
    professional_name: string;
    service_name: string;
};

const props = defineProps<{
    appointment: AppointmentInfo;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Agenda', href: index() },
            { title: 'Propor outro horário' },
        ],
    },
});

const form = useForm({
    starts_at: '',
});

const date = ref('');

const slotsBaseUrl = `${availableSlots().url}?${new URLSearchParams({
    unit_id: props.appointment.unit_id,
    professional_id: props.appointment.professional_id,
    service_id: props.appointment.service_id,
}).toString()}`;

function cancel() {
    router.get(index().url);
}

function submit() {
    form.put(updatePropose(props.appointment.id).url);
}
</script>

<template>
    <Head title="Propor outro horário" />

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Propor outro horário"
            :description="`${appointment.service_name} com ${appointment.professional_name}. O paciente precisará aceitar o novo horário pelo portal.`"
        />

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
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    :disabled="form.processing || !form.starts_at"
                >
                    Propor horário
                </Button>
            </div>
        </form>
    </div>
</template>

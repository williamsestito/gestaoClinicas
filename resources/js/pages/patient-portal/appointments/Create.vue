<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Clock3, Loader2 } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useAvailabilityCalendarGrid } from '@/composables/useAvailabilityCalendarGrid';
import { usePatientAvailabilitySearch } from '@/composables/usePatientAvailabilitySearch';
import patientPortal from '@/routes/patient-portal';

const props = defineProps<{
    patient: { id: string; name: string };
}>();

defineOptions({
    layout: {
        title: 'Novo agendamento',
    },
});

const {
    ANY_PROFESSIONAL,
    units,
    specialties,
    services,
    professionals,
    dates,
    times,
    selectedUnitId,
    selectedSpecialtyId,
    selectedServiceId,
    selectedProfessionalId,
    selectedDate,
    currentMonth,
    isLoading,
    error,
    isAnyProfessional,
    loadUnits,
    selectUnit,
    selectSpecialty,
    selectService,
    selectProfessional,
    selectDate,
    changeMonth,
} = usePatientAvailabilitySearch();

onMounted(() => {
    void loadUnits();
});

const form = useForm({
    unit_id: '',
    professional_id: '',
    service_id: '',
    starts_at: '',
    notes: '',
});

// O horário escolhido só existe enquanto os filtros que o originaram (data,
// profissional, serviço...) não mudarem — trocar qualquer um deles invalida
// a escolha anterior, evitando enviar um horário que não corresponde mais
// ao que está selecionado acima.
watch(
    [
        selectedUnitId,
        selectedSpecialtyId,
        selectedServiceId,
        selectedProfessionalId,
        selectedDate,
    ],
    () => {
        form.starts_at = '';
        chosenProfessionalName.value = '';
    },
);

const chosenProfessionalName = ref('');

const { monthLabel, calendarCells, shiftMonth } = useAvailabilityCalendarGrid(
    dates,
    currentMonth,
);

function goToPreviousMonth() {
    changeMonth(shiftMonth(-1));
}

function goToNextMonth() {
    changeMonth(shiftMonth(1));
}

function formatSelectedDate(): string {
    if (!selectedDate.value) {
        return '';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: 'long',
        weekday: 'long',
    }).format(new Date(`${selectedDate.value}T00:00:00`));
}

function selectSlot(slot: {
    time: string;
    professional_id: string;
    professional_name: string;
}) {
    if (
        !selectedUnitId.value ||
        !selectedServiceId.value ||
        !selectedDate.value
    ) {
        return;
    }

    form.unit_id = selectedUnitId.value;
    form.service_id = selectedServiceId.value;
    form.professional_id = slot.professional_id;
    form.starts_at = `${selectedDate.value}T${slot.time}:00`;
    chosenProfessionalName.value = slot.professional_name;
}

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
            <p class="text-muted-foreground text-sm">
                Para {{ patient.name }}. A clínica confirma o horário antes de
                ele valer como marcado.
            </p>
        </div>

        <form class="flex max-w-2xl flex-col gap-6" @submit.prevent="submit">
            <div
                class="border-border bg-card grid gap-4 rounded-2xl border p-6 shadow-sm"
            >
                <div
                    v-if="units.length === 0 && !isLoading('units')"
                    class="text-muted-foreground text-sm"
                >
                    Nenhuma unidade disponível para agendamento no momento.
                </div>

                <template v-else>
                    <div class="grid gap-2">
                        <Label for="availability-unit">Unidade</Label>
                        <Select
                            :model-value="selectedUnitId ?? ''"
                            @update:model-value="
                                (value) => value && selectUnit(String(value))
                            "
                        >
                            <SelectTrigger
                                id="availability-unit"
                                class="w-full"
                            >
                                <SelectValue
                                    placeholder="Selecione uma unidade"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="unit in units"
                                    :key="unit.id"
                                    :value="unit.id"
                                >
                                    {{ unit.name }}
                                    <span
                                        v-if="unit.city"
                                        class="text-muted-foreground"
                                    >
                                        —
                                        {{
                                            unit.neighborhood
                                                ? `${unit.neighborhood}, `
                                                : ''
                                        }}{{ unit.city }}/{{ unit.state }}
                                    </span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div v-if="selectedUnitId" class="grid gap-2">
                        <Label for="availability-specialty">
                            Especialidade (opcional)
                        </Label>
                        <Select
                            :model-value="selectedSpecialtyId ?? ''"
                            @update:model-value="
                                (value) =>
                                    selectSpecialty(
                                        value ? String(value) : null,
                                    )
                            "
                        >
                            <SelectTrigger
                                id="availability-specialty"
                                class="w-full"
                            >
                                <SelectValue
                                    placeholder="Selecione uma especialidade"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="specialty in specialties"
                                    :key="specialty.id"
                                    :value="specialty.id"
                                >
                                    {{ specialty.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div v-if="selectedUnitId" class="grid gap-2">
                        <Label for="availability-service">
                            Serviço ou procedimento
                        </Label>
                        <Select
                            :model-value="selectedServiceId ?? ''"
                            @update:model-value="
                                (value) => value && selectService(String(value))
                            "
                        >
                            <SelectTrigger
                                id="availability-service"
                                class="w-full"
                            >
                                <SelectValue
                                    placeholder="Selecione um serviço"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="service in services"
                                    :key="service.id"
                                    :value="service.id"
                                >
                                    {{ service.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="
                                selectedUnitId &&
                                services.length === 0 &&
                                !isLoading('services')
                            "
                            class="text-muted-foreground text-xs"
                        >
                            Nenhum serviço disponível para os filtros
                            selecionados.
                        </p>
                    </div>

                    <div v-if="selectedServiceId" class="grid gap-2">
                        <Label for="availability-professional">
                            Profissional
                        </Label>
                        <Select
                            :model-value="selectedProfessionalId ?? ''"
                            @update:model-value="
                                (value) =>
                                    value && selectProfessional(String(value))
                            "
                        >
                            <SelectTrigger
                                id="availability-professional"
                                class="w-full"
                            >
                                <SelectValue
                                    placeholder="Qualquer profissional disponível"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="ANY_PROFESSIONAL">
                                    Qualquer profissional disponível
                                </SelectItem>
                                <SelectItem
                                    v-for="professional in professionals"
                                    :key="professional.id"
                                    :value="professional.id"
                                >
                                    {{ professional.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div v-if="selectedServiceId" class="grid gap-3">
                        <div class="flex items-center justify-between">
                            <p
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <CalendarDays class="size-4" />
                                Selecione uma data
                            </p>
                            <div class="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="goToPreviousMonth"
                                    >Anterior</Button
                                >
                                <span
                                    class="min-w-32 text-center text-sm capitalize"
                                    >{{ monthLabel }}</span
                                >
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="goToNextMonth"
                                    >Próximo</Button
                                >
                            </div>
                        </div>

                        <div
                            v-if="isLoading('dates')"
                            class="text-muted-foreground flex items-center gap-2 text-sm"
                            aria-live="polite"
                        >
                            <Loader2 class="size-4 animate-spin" />
                            Carregando calendário…
                        </div>

                        <div
                            v-else
                            class="grid grid-cols-7 gap-1 text-center text-sm"
                        >
                            <span
                                v-for="label in [
                                    'D',
                                    'S',
                                    'T',
                                    'Q',
                                    'Q',
                                    'S',
                                    'S',
                                ]"
                                :key="label"
                                class="text-muted-foreground text-xs font-medium"
                                >{{ label }}</span
                            >
                            <template
                                v-for="(cell, index) in calendarCells"
                                :key="index"
                            >
                                <span v-if="cell === null"></span>
                                <button
                                    v-else
                                    type="button"
                                    :disabled="!cell.isAvailable"
                                    :aria-label="
                                        cell.isAvailable
                                            ? `Dia ${cell.day}, disponível`
                                            : `Dia ${cell.day}, indisponível`
                                    "
                                    :aria-pressed="selectedDate === cell.date"
                                    class="rounded-md py-1.5 text-sm"
                                    :class="[
                                        cell.isAvailable
                                            ? 'bg-primary/10 hover:bg-primary/20 cursor-pointer'
                                            : 'text-muted-foreground/50 cursor-not-allowed',
                                        selectedDate === cell.date &&
                                            'bg-primary text-primary-foreground hover:bg-primary',
                                    ]"
                                    @click="
                                        cell.isAvailable &&
                                        selectDate(cell.date)
                                    "
                                >
                                    {{ cell.day }}
                                </button>
                            </template>
                        </div>
                    </div>

                    <div v-if="selectedDate" class="grid gap-3">
                        <p class="flex items-center gap-2 text-sm font-medium">
                            <Clock3 class="size-4" />
                            Horários em {{ formatSelectedDate() }}
                        </p>

                        <div
                            v-if="isLoading('times')"
                            class="text-muted-foreground flex items-center gap-2 text-sm"
                            aria-live="polite"
                        >
                            <Loader2 class="size-4 animate-spin" />
                            Carregando horários…
                        </div>

                        <p
                            v-else-if="times.length === 0"
                            class="text-muted-foreground text-sm"
                        >
                            Nenhum horário disponível para os filtros
                            selecionados.
                        </p>

                        <div v-else class="flex flex-wrap gap-2">
                            <Button
                                v-for="(slot, index) in times"
                                :key="`${slot.professional_id}-${slot.time}-${index}`"
                                type="button"
                                :variant="
                                    form.starts_at ===
                                    `${selectedDate}T${slot.time}:00`
                                        ? 'default'
                                        : 'outline'
                                "
                                size="sm"
                                @click="selectSlot(slot)"
                            >
                                {{ slot.time }}
                                <span
                                    v-if="isAnyProfessional"
                                    class="text-muted-foreground"
                                    >— {{ slot.professional_name }}</span
                                >
                            </Button>
                        </div>
                    </div>

                    <p
                        v-if="error"
                        role="alert"
                        class="text-destructive text-sm"
                    >
                        {{ error }}
                    </p>
                </template>

                <InputError :message="form.errors.starts_at" />
            </div>

            <p
                v-if="form.starts_at"
                class="text-muted-foreground text-sm"
                role="status"
            >
                Horário selecionado: {{ formatSelectedDate() }} às
                {{ form.starts_at.slice(11, 16) }}
                <template v-if="chosenProfessionalName">
                    com {{ chosenProfessionalName }}
                </template>
                .
            </p>

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

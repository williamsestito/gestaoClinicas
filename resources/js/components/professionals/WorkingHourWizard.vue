<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import WizardSteps from '@/components/organization/WizardSteps.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { configure } from '@/routes/settings/professionals/working-hours';

const WEEKDAY_OPTIONS = [
    { value: 1, label: 'Segunda-feira' },
    { value: 2, label: 'Terça-feira' },
    { value: 3, label: 'Quarta-feira' },
    { value: 4, label: 'Quinta-feira' },
    { value: 5, label: 'Sexta-feira' },
    { value: 6, label: 'Sábado' },
    { value: 0, label: 'Domingo' },
];

const STEP_TITLES = ['Vigência', 'Dias da semana', 'Intervalos', 'Resumo'];

const props = defineProps<{
    professionalId: string;
    professionalUnitId: string;
}>();

const emit = defineEmits<{
    success: [];
    cancel: [];
}>();

const step = ref(0);

const form = useForm({
    effective_from: '',
    effective_until: '',
    // Segunda a sexta pré-selecionado como sugestão — sábado e domingo só
    // entram na jornada se o profissional marcar explicitamente.
    weekdays: [1, 2, 3, 4, 5] as number[],
    intervals: [{ starts_at: '', ends_at: '' }] as {
        starts_at: string;
        ends_at: string;
    }[],
});

function toggleWeekday(value: number, checked: boolean) {
    if (checked) {
        form.weekdays = [...form.weekdays, value].sort((a, b) => a - b);

        return;
    }

    form.weekdays = form.weekdays.filter((day) => day !== value);
}

function addInterval() {
    form.intervals = [...form.intervals, { starts_at: '', ends_at: '' }];
}

function removeInterval(index: number) {
    form.intervals = form.intervals.filter((_, i) => i !== index);
}

const vigencyValid = computed(
    () =>
        form.effective_from !== '' &&
        form.effective_until !== '' &&
        form.effective_from <= form.effective_until,
);

const weekdaysValid = computed(() => form.weekdays.length > 0);

const intervalsValid = computed(() => {
    if (form.intervals.length === 0) {
        return false;
    }

    return form.intervals.every(
        (interval) =>
            interval.starts_at !== '' &&
            interval.ends_at !== '' &&
            interval.starts_at < interval.ends_at,
    );
});

const canProceed = computed(() => {
    if (step.value === 0) {
        return vigencyValid.value;
    }

    if (step.value === 1) {
        return weekdaysValid.value;
    }

    if (step.value === 2) {
        return intervalsValid.value;
    }

    return true;
});

function goNext() {
    if (canProceed.value && step.value < STEP_TITLES.length - 1) {
        step.value += 1;
    }
}

function goBack() {
    if (step.value > 0) {
        step.value -= 1;
    }
}

// Estimativa só para orientar o profissional no resumo — o cálculo real e
// definitivo de dias elegíveis (considerando bloqueios já existentes) é
// feito pelo backend (ProfessionalAvailabilityCalendarResolver) sob
// demanda depois de salvar, e nunca é persistido aqui.
const estimatedDays = computed(() => {
    if (!vigencyValid.value || !weekdaysValid.value) {
        return 0;
    }

    const start = new Date(`${form.effective_from}T00:00:00`);
    const end = new Date(`${form.effective_until}T00:00:00`);

    if (start > end) {
        return 0;
    }

    let count = 0;
    const cursor = new Date(start);

    while (cursor <= end) {
        if (form.weekdays.includes(cursor.getDay())) {
            count += 1;
        }

        cursor.setDate(cursor.getDate() + 1);
    }

    return count;
});

const selectedWeekdayLabels = computed(() =>
    WEEKDAY_OPTIONS.filter((day) => form.weekdays.includes(day.value))
        .map((day) => day.label)
        .join(', '),
);

function submit() {
    form.post(configure([props.professionalId, props.professionalUnitId]).url, {
        preserveScroll: true,
        onSuccess: () => emit('success'),
    });
}
</script>

<template>
    <div class="grid gap-5">
        <WizardSteps :steps="STEP_TITLES" :current="step" />

        <div v-if="step === 0" class="grid gap-4">
            <p class="text-muted-foreground text-sm">
                Defina o período em que esses horários valem. Fora dessa
                vigência, o profissional não aparece como disponível.
            </p>
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="wizard-effective-from">Vigência inicial</Label>
                    <Input
                        id="wizard-effective-from"
                        v-model="form.effective_from"
                        type="date"
                    />
                    <InputError :message="form.errors.effective_from" />
                </div>
                <div class="grid gap-2">
                    <Label for="wizard-effective-until">Vigência final</Label>
                    <Input
                        id="wizard-effective-until"
                        v-model="form.effective_until"
                        type="date"
                    />
                    <InputError :message="form.errors.effective_until" />
                </div>
            </div>
        </div>

        <div v-else-if="step === 1" class="grid gap-4">
            <p class="text-muted-foreground text-sm">
                Segunda a sexta já vem marcado como sugestão. Marque sábado e/ou
                domingo somente se o profissional atender nesses dias.
            </p>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                <label
                    v-for="day in WEEKDAY_OPTIONS"
                    :key="day.value"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        :aria-label="day.label"
                        :checked="form.weekdays.includes(day.value)"
                        @change="
                            toggleWeekday(
                                day.value,
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    {{ day.label }}
                </label>
            </div>
            <InputError :message="form.errors.weekdays" />
        </div>

        <div v-else-if="step === 2" class="grid gap-4">
            <p class="text-muted-foreground text-sm">
                Esses intervalos se aplicam a todos os dias selecionados na
                etapa anterior. Adicione mais de um intervalo para representar
                um intervalo de almoço, por exemplo.
            </p>
            <div class="grid gap-3">
                <div
                    v-for="(interval, index) in form.intervals"
                    :key="index"
                    class="grid grid-cols-[1fr_1fr_auto] items-end gap-2"
                >
                    <div class="grid gap-2">
                        <Label :for="`wizard-interval-start-${index}`"
                            >Início</Label
                        >
                        <Input
                            :id="`wizard-interval-start-${index}`"
                            v-model="interval.starts_at"
                            type="time"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`wizard-interval-end-${index}`">Fim</Label>
                        <Input
                            :id="`wizard-interval-end-${index}`"
                            v-model="interval.ends_at"
                            type="time"
                        />
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="form.intervals.length === 1"
                        :aria-label="`Remover intervalo ${index + 1}`"
                        @click="removeInterval(index)"
                    >
                        Remover
                    </Button>
                </div>
            </div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="justify-self-start"
                @click="addInterval"
            >
                Adicionar intervalo
            </Button>
            <InputError :message="form.errors.intervals" />
        </div>

        <div v-else class="grid gap-3">
            <div class="grid gap-1 rounded-md border p-3 text-sm">
                <p>
                    <span class="font-medium">Vigência:</span>
                    {{ form.effective_from }} até {{ form.effective_until }}
                </p>
                <p>
                    <span class="font-medium">Dias:</span>
                    {{ selectedWeekdayLabels }}
                </p>
                <p>
                    <span class="font-medium">Intervalos:</span>
                    {{ form.intervals.length }}
                </p>
                <p>
                    <span class="font-medium"
                        >Estimativa de dias com atendimento:</span
                    >
                    {{ estimatedDays }}
                </p>
            </div>
            <p class="text-muted-foreground text-xs">
                Essa estimativa considera só os dias da semana escolhidos dentro
                da vigência — bloqueios de data já cadastrados são aplicados
                automaticamente na disponibilidade final, sem precisar
                recalcular aqui.
            </p>
            <InputError :message="form.errors.weekdays" />
            <InputError :message="form.errors.intervals" />
        </div>

        <div class="flex items-center justify-between gap-2 pt-2">
            <Button
                type="button"
                variant="secondary"
                :disabled="form.processing"
                @click="step === 0 ? emit('cancel') : goBack()"
            >
                {{ step === 0 ? 'Cancelar' : 'Voltar' }}
            </Button>

            <Button
                v-if="step < STEP_TITLES.length - 1"
                type="button"
                :disabled="!canProceed"
                @click="goNext"
            >
                Próximo
            </Button>
            <Button
                v-else
                type="button"
                :disabled="form.processing"
                @click="submit"
            >
                Confirmar e salvar
            </Button>
        </div>
    </div>
</template>

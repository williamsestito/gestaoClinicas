<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import PatientSearchSelect from '@/components/appointments/PatientSearchSelect.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import {
    availableSlots,
    index,
    patientSessionPackages,
    store,
} from '@/routes/settings/appointments';

type Option = { id: string; name?: string; display_name?: string };
type ResourceOption = { id: string; unit_id: string; name: string };
type Slot = { time: string; duration_minutes: number };
type SessionPackageOption = {
    id: string;
    service_id: string | null;
    service_name: string | null;
    remaining_sessions: number;
};
type Prefill = {
    appointment_request_id?: string;
    waitlist_entry_id?: string;
    name: string;
    phone: string;
    notes: string | null;
    unit_id?: string | null;
    professional_id?: string | null;
};

const props = defineProps<{
    units: Option[];
    professionals: Option[];
    services: Option[];
    resources: ResourceOption[];
    prefill: Prefill | null;
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
    unit_id: props.prefill?.unit_id ?? '',
    professional_id: props.prefill?.professional_id ?? '',
    patient_id: '',
    service_id: '',
    starts_at: '',
    notes: props.prefill?.notes ?? '',
    appointment_request_id: props.prefill?.appointment_request_id ?? '',
    waitlist_entry_id: props.prefill?.waitlist_entry_id ?? '',
    resource_ids: [] as string[],
    session_package_id: '',
    recurrence_weeks: undefined as number | undefined,
});

// Unidade/profissional já conhecidos (a solicitação de origem já os
// carrega) ficam travados — evita fazer quem está convertendo reescolher o
// que já está decidido (ver AppointmentController::create()).
const isUnitLocked = computed(() => !!props.prefill?.unit_id);
const isProfessionalLocked = computed(() => !!props.prefill?.professional_id);
const lockedUnitName = computed(
    () => props.units.find((unit) => unit.id === props.prefill?.unit_id)?.name,
);
const lockedProfessionalName = computed(
    () =>
        props.professionals.find(
            (professional) =>
                professional.id === props.prefill?.professional_id,
        )?.display_name,
);

const repeatWeekly = ref(false);

watch(repeatWeekly, (value) => {
    form.recurrence_weeks = value ? 4 : undefined;
});

const date = ref('');
const slots = ref<Slot[]>([]);
const loadingSlots = ref(false);
const sessionPackages = ref<SessionPackageOption[]>([]);

const sessionPackagesForSelectedService = computed(() =>
    sessionPackages.value.filter(
        (pkg) => pkg.service_id === null || pkg.service_id === form.service_id,
    ),
);

async function loadSessionPackages() {
    form.session_package_id = '';

    if (!form.patient_id) {
        sessionPackages.value = [];

        return;
    }

    const response = await fetch(patientSessionPackages(form.patient_id).url, {
        headers: { Accept: 'application/json' },
    });

    sessionPackages.value = response.ok
        ? ((await response.json()) as { packages: SessionPackageOption[] })
              .packages
        : [];
}

watch(() => form.patient_id, loadSessionPackages);

const resourcesForSelectedUnit = computed(() =>
    props.resources.filter((resource) => resource.unit_id === form.unit_id),
);

function toggleResource(resourceId: string, checked: boolean) {
    if (checked) {
        form.resource_ids = [...form.resource_ids, resourceId];

        return;
    }

    form.resource_ids = form.resource_ids.filter((id) => id !== resourceId);
}

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

    <div class="flex flex-col space-y-6 p-4">
        <PageHeader
            title="Novo agendamento"
            description="Crie um agendamento real para um paciente já cadastrado."
        />

        <div
            v-if="prefill"
            class="max-w-2xl rounded-lg border border-primary/30 bg-primary/5 p-4 text-sm"
        >
            <p class="font-medium">
                {{
                    prefill.waitlist_entry_id
                        ? 'Convertendo entrada da lista de espera:'
                        : 'Convertendo lead:'
                }}
                {{ prefill.name }} ({{ prefill.phone }})
            </p>
            <p class="text-muted-foreground">
                Busque o paciente correspondente abaixo ou cadastre um novo
                paciente antes de continuar.
            </p>
        </div>

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
                    <p
                        v-if="isUnitLocked"
                        id="appointment-unit"
                        class="flex h-9 items-center rounded-md border border-input bg-muted px-3 text-sm"
                    >
                        {{ lockedUnitName }}
                    </p>
                    <select
                        v-else
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
                    <p
                        v-if="isProfessionalLocked"
                        id="appointment-professional"
                        class="flex h-9 items-center rounded-md border border-input bg-muted px-3 text-sm"
                    >
                        {{ lockedProfessionalName }}
                    </p>
                    <select
                        v-else
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

            <div
                v-if="form.unit_id && resourcesForSelectedUnit.length > 0"
                class="grid gap-2"
            >
                <Label>Recursos (salas/equipamentos, opcional)</Label>
                <div class="flex flex-col gap-2">
                    <label
                        v-for="resource in resourcesForSelectedUnit"
                        :key="resource.id"
                        class="flex items-center gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            :checked="form.resource_ids.includes(resource.id)"
                            @change="
                                toggleResource(
                                    resource.id,
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                        {{ resource.name }}
                    </label>
                </div>
                <InputError :message="form.errors.resource_ids" />
            </div>

            <div
                v-if="sessionPackagesForSelectedService.length > 0"
                class="grid gap-2"
            >
                <Label for="appointment-session-package">
                    Descontar de pacote de sessões (opcional)
                </Label>
                <select
                    id="appointment-session-package"
                    v-model="form.session_package_id"
                    class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="">Não descontar de pacote</option>
                    <option
                        v-for="pkg in sessionPackagesForSelectedService"
                        :key="pkg.id"
                        :value="pkg.id"
                    >
                        {{ pkg.service_name ?? 'Qualquer serviço' }} —
                        {{ pkg.remaining_sessions }} restante(s)
                    </option>
                </select>
                <InputError :message="form.errors.session_package_id" />
            </div>

            <div class="grid gap-2">
                <Label class="flex items-center gap-2 font-normal">
                    <Checkbox v-model:model-value="repeatWeekly" />
                    Repetir semanalmente
                </Label>
                <div v-if="repeatWeekly" class="grid max-w-40 gap-2">
                    <Label for="appointment-recurrence-weeks">
                        Número de semanas
                    </Label>
                    <Input
                        id="appointment-recurrence-weeks"
                        v-model.number="form.recurrence_weeks"
                        type="number"
                        min="2"
                        max="52"
                    />
                    <InputError :message="form.errors.recurrence_weeks" />
                </div>
            </div>

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

<script setup lang="ts">
import { CalendarDays, Clock3, Loader2 } from '@lucide/vue';
import { computed, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useLandingScheduling } from '@/composables/useLandingScheduling';
import { usePublicAvailabilitySearch } from '@/composables/usePublicAvailabilitySearch';

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
} = usePublicAvailabilitySearch();

// Nota: o serviço aqui é o cadastro operacional (ULID), enquanto o
// formulário manual de solicitação referencia o serviço promocional
// (SiteService, id numérico) — são espaços de id diferentes, nunca
// convertidos entre si. Por isso só preenchemos o campo de observações
// (texto livre) e os campos estruturados de data/período, nunca o campo
// `service_id` do formulário manual. Profissional é diferente: tanto aqui
// quanto em `appointment_requests.professional_id` é o mesmo cadastro
// operacional (ULID) — por isso `sharedSelectedProfessionalId` pode ser
// preenchido diretamente, sem o mesmo cuidado de conversão. Renomeado no
// destructure (`as`) porque `usePublicAvailabilitySearch()` já expõe seu
// próprio `selectedProfessionalId` local — o filtro de profissional da
// busca, um conceito diferente deste (o vínculo real a ser enviado com a
// solicitação manual).
const {
    selectedProfessionalId: sharedSelectedProfessionalId,
    selectedProfessionalName,
    preferredDate,
    preferredPeriod,
} = useLandingScheduling();

onMounted(() => {
    void loadUnits();
});

const monthLabel = computed(() => {
    const [year, month] = currentMonth.value.split('-').map(Number);

    return new Intl.DateTimeFormat('pt-BR', {
        month: 'long',
        year: 'numeric',
    }).format(new Date(year, month - 1, 1));
});

const calendarCells = computed(() => {
    const [year, month] = currentMonth.value.split('-').map(Number);
    const firstDay = new Date(year, month - 1, 1);
    const leadingBlanks = firstDay.getDay();
    const byDate = new Map(
        dates.value.map((day) => [day.date, day.is_available]),
    );

    type Cell = { date: string; day: number; isAvailable: boolean } | null;

    const blanks: Cell[] = Array.from({ length: leadingBlanks }, () => null);
    const days: Cell[] = dates.value.map((day) => ({
        date: day.date,
        day: Number(day.date.slice(8, 10)),
        isAvailable: byDate.get(day.date) ?? false,
    }));

    return blanks.concat(days);
});

function goToPreviousMonth() {
    const [year, month] = currentMonth.value.split('-').map(Number);
    const previous = new Date(year, month - 2, 1);
    changeMonth(
        `${previous.getFullYear()}-${String(previous.getMonth() + 1).padStart(2, '0')}`,
    );
}

function goToNextMonth() {
    const [year, month] = currentMonth.value.split('-').map(Number);
    const next = new Date(year, month, 1);
    changeMonth(
        `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`,
    );
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

function periodForTime(time: string): string {
    const hour = Number(time.slice(0, 2));

    if (hour < 12) {
        return 'Manhã';
    }

    return hour < 18 ? 'Tarde' : 'Noite';
}

function chooseTimeForScheduling(slot: {
    time: string;
    professional_id: string;
    professional_name: string;
    service_name: string;
}) {
    const service = services.value.find(
        (item) => item.id === selectedServiceId.value,
    );
    const serviceName = service?.name ?? slot.service_name;
    const dateLabel = formatSelectedDate();

    sharedSelectedProfessionalId.value = slot.professional_id;
    selectedProfessionalName.value = `${slot.professional_name} — ${serviceName}, ${dateLabel} às ${slot.time}`;
    preferredDate.value = selectedDate.value;
    preferredPeriod.value = periodForTime(slot.time);

    // Vai direto para os dados pessoais (nome/telefone), não para o topo
    // da seção inteira — a pessoa já escolheu unidade/especialidade/
    // serviço/profissional/data/horário, o próximo passo é completar o
    // contato. A validação só acontece quando ela confirmar, no botão
    // "Criar pré-agendamento" mais abaixo.
    document
        .getElementById('name')
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>

<template>
    <div
        class="mb-10 rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8"
    >
        <div class="mb-6">
            <p class="landing-eyebrow mb-2">Consulte a disponibilidade</p>
            <h3 class="text-xl font-semibold tracking-tight">
                Encontre um horário
            </h3>
            <p class="mt-1 text-sm text-muted-foreground">
                Selecione a unidade, a especialidade e o serviço para ver as
                datas e horários teoricamente disponíveis.
            </p>
        </div>

        <div
            v-if="units.length === 0 && !isLoading('units')"
            class="text-sm text-muted-foreground"
        >
            Nenhuma unidade disponível para consulta no momento.
        </div>

        <div v-else class="grid gap-4">
            <div class="grid gap-2">
                <label for="availability-unit" class="text-sm font-medium"
                    >Unidade</label
                >
                <Select
                    :model-value="selectedUnitId ?? ''"
                    @update:model-value="
                        (value) => value && selectUnit(String(value))
                    "
                >
                    <SelectTrigger id="availability-unit" class="w-full">
                        <SelectValue placeholder="Selecione uma unidade" />
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
                <label for="availability-specialty" class="text-sm font-medium"
                    >Especialidade</label
                >
                <Select
                    :model-value="selectedSpecialtyId ?? ''"
                    @update:model-value="
                        (value) => selectSpecialty(value ? String(value) : null)
                    "
                >
                    <SelectTrigger id="availability-specialty" class="w-full">
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
                <label for="availability-service" class="text-sm font-medium"
                    >Serviço ou procedimento</label
                >
                <Select
                    :model-value="selectedServiceId ?? ''"
                    @update:model-value="
                        (value) => value && selectService(String(value))
                    "
                >
                    <SelectTrigger id="availability-service" class="w-full">
                        <SelectValue placeholder="Selecione um serviço" />
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
                    class="text-xs text-muted-foreground"
                >
                    Nenhum serviço disponível para os filtros selecionados.
                </p>
            </div>

            <div v-if="selectedServiceId" class="grid gap-2">
                <label
                    for="availability-professional"
                    class="text-sm font-medium"
                    >Profissional</label
                >
                <Select
                    :model-value="selectedProfessionalId ?? ''"
                    @update:model-value="
                        (value) => value && selectProfessional(String(value))
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
                    <p class="flex items-center gap-2 text-sm font-medium">
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
                        <span class="min-w-32 text-center text-sm capitalize">{{
                            monthLabel
                        }}</span>
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
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                    aria-live="polite"
                >
                    <Loader2 class="size-4 animate-spin" />
                    Carregando calendário…
                </div>

                <div v-else class="grid grid-cols-7 gap-1 text-center text-sm">
                    <span
                        v-for="label in ['D', 'S', 'T', 'Q', 'Q', 'S', 'S']"
                        :key="label"
                        class="text-xs font-medium text-muted-foreground"
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
                                    ? 'cursor-pointer bg-primary/10 hover:bg-primary/20'
                                    : 'cursor-not-allowed text-muted-foreground/50',
                                selectedDate === cell.date &&
                                    'bg-primary text-primary-foreground hover:bg-primary',
                            ]"
                            @click="cell.isAvailable && selectDate(cell.date)"
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
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                    aria-live="polite"
                >
                    <Loader2 class="size-4 animate-spin" />
                    Carregando horários…
                </div>

                <p
                    v-else-if="times.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Nenhum horário disponível para os filtros selecionados.
                </p>

                <div v-else class="flex flex-wrap gap-2">
                    <Button
                        v-for="(slot, index) in times"
                        :key="`${slot.professional_id}-${slot.time}-${index}`"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="chooseTimeForScheduling(slot)"
                    >
                        {{ slot.time }}
                        <span
                            v-if="isAnyProfessional"
                            class="text-muted-foreground"
                            >— {{ slot.professional_name }}</span
                        >
                    </Button>
                </div>

                <p
                    v-if="times.length > 0"
                    class="text-xs text-muted-foreground"
                >
                    Escolha um horário para preencher automaticamente seus dados
                    de contato abaixo. Nada é reservado agora — o
                    pré-agendamento só é enviado quando você confirmar no
                    formulário.
                </p>
            </div>

            <p v-if="error" role="alert" class="text-sm text-destructive">
                {{ error }}
            </p>
        </div>
    </div>
</template>

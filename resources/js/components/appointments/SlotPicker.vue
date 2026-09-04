<script setup lang="ts">
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Slot = { time: string; duration_minutes: number };

const props = defineProps<{
    /** URL base já com os parâmetros fixos (ex.: unit_id/professional_id/service_id) — sem `date`. Null desabilita a busca. */
    baseUrl: string | null;
    error?: string;
}>();

const date = defineModel<string>('date', { default: '' });
const startsAt = defineModel<string>('startsAt', { default: '' });

const slots = ref<Slot[]>([]);
const loadingSlots = ref(false);

async function loadSlots() {
    if (!props.baseUrl || !date.value) {
        slots.value = [];

        return;
    }

    loadingSlots.value = true;
    startsAt.value = '';

    try {
        const separator = props.baseUrl.includes('?') ? '&' : '?';
        const response = await fetch(
            `${props.baseUrl}${separator}date=${date.value}`,
            { headers: { Accept: 'application/json' } },
        );

        slots.value = response.ok
            ? ((await response.json()) as { slots: Slot[] }).slots
            : [];
    } finally {
        loadingSlots.value = false;
    }
}

watch([() => props.baseUrl, date], loadSlots);

function selectSlot(time: string) {
    startsAt.value = `${date.value}T${time}:00`;
}
</script>

<template>
    <div class="grid gap-4">
        <div class="grid max-w-52 gap-2">
            <Label for="slot-picker-date">Data</Label>
            <Input id="slot-picker-date" v-model="date" type="date" />
        </div>

        <div v-if="date" class="grid gap-2">
            <Label>Horários disponíveis</Label>
            <p v-if="loadingSlots" class="text-muted-foreground text-sm">
                Buscando horários…
            </p>
            <p
                v-else-if="slots.length === 0"
                class="text-muted-foreground text-sm"
            >
                Nenhum horário livre encontrado para os filtros selecionados.
            </p>
            <div v-else class="flex flex-wrap gap-2">
                <Button
                    v-for="slot in slots"
                    :key="slot.time"
                    type="button"
                    :variant="
                        startsAt === `${date}T${slot.time}:00`
                            ? 'default'
                            : 'outline'
                    "
                    size="sm"
                    @click="selectSlot(slot.time)"
                >
                    {{ slot.time }}
                </Button>
            </div>
            <InputError :message="error" />
        </div>
    </div>
</template>

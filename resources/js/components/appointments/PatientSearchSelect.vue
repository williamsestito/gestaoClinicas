<script setup lang="ts">
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { search } from '@/routes/settings/patients';

type PatientOption = {
    id: string;
    name: string;
    birth_date: string;
};

const selectedId = defineModel<string>({ default: '' });

defineProps<{
    error?: string;
}>();

const query = ref('');
const results = ref<PatientOption[]>([]);
const selectedName = ref('');
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(query, (value) => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    if (value.trim().length < 2) {
        results.value = [];

        return;
    }

    debounceTimer = setTimeout(async () => {
        const response = await fetch(
            `${search().url}?q=${encodeURIComponent(value)}`,
            { headers: { Accept: 'application/json' } },
        );

        if (response.ok) {
            const data = (await response.json()) as {
                patients: PatientOption[];
            };
            results.value = data.patients;
        }
    }, 300);
});

function select(patient: PatientOption) {
    selectedId.value = patient.id;
    selectedName.value = patient.name;
    query.value = '';
    results.value = [];
}

function clear() {
    selectedId.value = '';
    selectedName.value = '';
}
</script>

<template>
    <div class="grid gap-2">
        <div
            v-if="selectedId"
            class="flex items-center justify-between rounded-md border p-2 text-sm"
        >
            <span>{{ selectedName }}</span>
            <Button type="button" variant="ghost" size="sm" @click="clear">
                Trocar
            </Button>
        </div>
        <template v-else>
            <Input
                v-model="query"
                placeholder="Buscar paciente por nome ou CPF…"
            />
            <ul
                v-if="results.length"
                class="divide-y rounded-md border text-sm"
            >
                <li v-for="patient in results" :key="patient.id">
                    <button
                        type="button"
                        class="w-full p-2 text-left hover:bg-muted"
                        @click="select(patient)"
                    >
                        {{ patient.name }}
                    </button>
                </li>
            </ul>
        </template>
        <InputError :message="error" />
    </div>
</template>

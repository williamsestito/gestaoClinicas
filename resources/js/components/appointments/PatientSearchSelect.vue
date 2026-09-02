<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, search } from '@/routes/settings/patients';

type PatientOption = {
    id: string;
    name: string;
    birth_date: string;
};

const selectedId = defineModel<string>({ default: '' });

const props = defineProps<{
    error?: string;
    /**
     * Dados já digitados pelo lead (nome/telefone/e-mail/CPF), reaproveitados
     * no link "Cadastrar novo paciente" abaixo — evita que quem está
     * confirmando o agendamento tenha que redigitar tudo de novo numa aba
     * separada quando o paciente ainda não existe (ver docs/roadmap.md).
     */
    prefillForNewPatient?: {
        name?: string;
        phone?: string;
        email?: string;
        document?: string;
    };
}>();

const newPatientUrl = computed(() => {
    const params = new URLSearchParams();
    const prefill = props.prefillForNewPatient;

    if (prefill?.name) {
        params.set('name', prefill.name);
    }

    if (prefill?.phone) {
        params.set('phone', prefill.phone);
    }

    if (prefill?.email) {
        params.set('email', prefill.email);
    }

    if (prefill?.document) {
        params.set('document', prefill.document);
    }

    const queryString = params.toString();

    return queryString ? `${create().url}?${queryString}` : create().url;
});

const query = ref('');
const results = ref<PatientOption[]>([]);
const selectedName = ref('');
const hasSearched = ref(false);
const searchFailed = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(query, (value) => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    if (value.trim().length < 2) {
        results.value = [];
        hasSearched.value = false;
        searchFailed.value = false;

        return;
    }

    debounceTimer = setTimeout(async () => {
        try {
            const response = await fetch(
                `${search().url}?q=${encodeURIComponent(value)}`,
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok) {
                results.value = [];
                searchFailed.value = true;

                return;
            }

            const data = (await response.json()) as {
                patients: PatientOption[];
            };
            results.value = data.patients;
            searchFailed.value = false;
        } catch {
            results.value = [];
            searchFailed.value = true;
        } finally {
            hasSearched.value = true;
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
            <p v-else-if="searchFailed" class="text-sm text-destructive">
                Não foi possível buscar pacientes agora. Tente novamente ou peça
                a alguém com acesso administrativo.
            </p>
            <p v-else-if="hasSearched" class="text-sm text-muted-foreground">
                Nenhum paciente encontrado com esse nome/CPF.
                <a
                    :href="newPatientUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="font-medium text-foreground underline underline-offset-2"
                >
                    Cadastre o paciente
                </a>
                numa aba nova e busque de novo aqui para confirmar.
            </p>
            <a
                v-else
                :href="newPatientUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="w-fit text-xs text-muted-foreground underline underline-offset-2"
            >
                Paciente novo? Cadastrar antes de confirmar
            </a>
        </template>
        <InputError :message="error" />
    </div>
</template>

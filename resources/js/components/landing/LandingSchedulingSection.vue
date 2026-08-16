<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useLandingScheduling } from '@/composables/useLandingScheduling';
import { store } from '@/routes/appointment-requests';
import LandingAvailabilitySearch from './LandingAvailabilitySearch.vue';

const {
    selectedServiceId,
    selectedProfessionalName,
    preferredDate,
    preferredPeriod,
} = useLandingScheduling();

const STEPS = [
    {
        title: 'Escolha o serviço',
        description: 'Selecione o tratamento de interesse (opcional).',
    },
    {
        title: 'Preencha seus dados',
        description: 'Nome e telefone/WhatsApp para contato.',
    },
    {
        title: 'Indique sua preferência',
        description: 'Data e período aproximados — não é uma reserva.',
    },
    {
        title: 'Aguarde a confirmação',
        description:
            'Nossa equipe confirma o horário pelo telefone ou WhatsApp.',
    },
];

const PERIOD_OPTIONS = ['Manhã', 'Tarde', 'Noite'];

const today = new Date();
const minPreferredDate = today.toISOString().slice(0, 10);
const maxPreferredDateValue = new Date(today);
maxPreferredDateValue.setDate(maxPreferredDateValue.getDate() + 90);
const maxPreferredDate = maxPreferredDateValue.toISOString().slice(0, 10);

/**
 * Parâmetros de origem (utm_*, ref, referrer, URL da página) capturados uma
 * única vez ao montar o formulário — apenas para análise de origem no
 * administrativo, nunca usados para autorização ou execução de código.
 */
function captureUtm(): Record<string, string> {
    if (typeof window === 'undefined') {
        return {};
    }

    const params = new URLSearchParams(window.location.search);
    const utm: Record<string, string> = {};

    for (const key of [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'utm_id',
        'utm_source_platform',
    ]) {
        const value = params.get(key);

        if (value) {
            utm[key] = value;
        }
    }

    const ref = params.get('ref');

    if (ref) {
        utm.ref = ref;
    }

    if (document.referrer) {
        utm.referrer = document.referrer;
    }

    utm.page_url = window.location.href;

    return utm;
}

const form = useForm({
    service_id: null as number | null,
    name: '',
    phone: '',
    email: '',
    preferred_period: '',
    preferred_date: '',
    notes: '',
    terms_accepted: false,
    // Honeypot: campo escondido da navegação real; um preenchimento aqui só
    // acontece por automação (ver PublicAppointmentRequestController).
    website: '',
    form_rendered_at: Date.now(),
    utm: captureUtm(),
});

watch(
    selectedServiceId,
    (id) => {
        if (id !== null) {
            form.service_id = id;
        }
    },
    { immediate: true },
);

// Não há seleção real de profissional no formulário (só o serviço tem
// coluna própria em `appointment_requests`) — clicar em "Agendar" num
// profissional, ou num horário da busca de disponibilidade, preenche as
// observações automaticamente. Cliques repetidos (ex.: escolher outro
// horário) continuam atualizando o texto — só paramos de sobrescrever
// quando a pessoa edita as observações com as próprias palavras.
let lastAutoFilledNotes = '';

watch(
    selectedProfessionalName,
    (name) => {
        if (!name) {
            return;
        }

        if (form.notes === '' || form.notes === lastAutoFilledNotes) {
            form.notes = `Gostaria de agendar com ${name}.`;
            lastAutoFilledNotes = form.notes;
        }
    },
    { immediate: true },
);

// Data/período são campos estruturados (não texto livre), então sempre
// refletem o último horário escolhido na busca de disponibilidade —
// diferente das observações, aqui não faz sentido "proteger" contra
// sobrescrita: cada clique num horário representa a escolha atual.
watch(
    preferredDate,
    (date) => {
        if (date) {
            form.preferred_date = date;
        }
    },
    { immediate: true },
);

watch(
    preferredPeriod,
    (period) => {
        if (period) {
            form.preferred_period = period;
        }
    },
    { immediate: true },
);

function submit() {
    // A validação (nome, telefone, aceite dos termos etc.) só acontece
    // aqui, ao confirmar — nunca antes, ao escolher serviço/horário.
    // form.recentlySuccessful já protege contra clique duplo (botão fica
    // desabilitado enquanto form.processing é true).
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <section
        id="scheduling"
        class="mx-auto max-w-2xl scroll-mt-16 px-4 py-16 sm:px-6"
    >
        <div class="mb-8 text-center">
            <p class="landing-eyebrow mb-2">Como funciona</p>
            <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                Agende sua avaliação
            </h2>
            <p class="mt-2 text-muted-foreground">
                Preencha seus dados e entraremos em contato para confirmar o
                melhor horário.
            </p>
        </div>

        <ol class="mb-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <li
                v-for="(step, index) in STEPS"
                :key="step.title"
                class="flex flex-col items-center gap-2 text-center"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-full text-lg font-semibold"
                    style="
                        font-family: var(--landing-font-title);
                        background-color: var(--landing-accent-soft);
                        color: var(--landing-primary);
                    "
                >
                    {{ index + 1 }}
                </span>
                <p class="text-sm font-medium">{{ step.title }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ step.description }}
                </p>
            </li>
        </ol>

        <LandingAvailabilitySearch />

        <div
            v-if="form.recentlySuccessful"
            class="flex flex-col items-center gap-2 rounded-xl border border-primary/30 bg-primary/5 p-6 text-center"
            role="status"
        >
            <CheckCircle2 class="size-8 text-primary" />
            <p class="font-medium">Pré-agendamento enviado!</p>
            <p class="text-sm text-muted-foreground">
                Sua solicitação foi encaminhada à clínica. Você receberá a
                confirmação do procedimento em breve, pelo telefone ou WhatsApp
                informado.
            </p>
        </div>

        <form
            v-else
            class="grid gap-5 rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8"
            @submit.prevent="submit"
        >
            <div class="grid gap-2">
                <Label for="name">Nome completo</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    required
                    autocomplete="name"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="phone">Telefone / WhatsApp</Label>
                    <PhoneInput
                        id="phone"
                        v-model="form.phone"
                        required
                        autocomplete="tel"
                    />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-2">
                    <Label for="email">E-mail (opcional)</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                    />
                    <InputError :message="form.errors.email" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="preferred_date"
                        >Data preferencial (opcional)</Label
                    >
                    <Input
                        id="preferred_date"
                        v-model="form.preferred_date"
                        type="date"
                        :min="minPreferredDate"
                        :max="maxPreferredDate"
                    />
                    <InputError :message="form.errors.preferred_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="preferred_period">Preferência de período</Label>
                    <Select v-model="form.preferred_period">
                        <SelectTrigger id="preferred_period" class="w-full">
                            <SelectValue placeholder="Sem preferência" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="period in PERIOD_OPTIONS"
                                :key="period"
                                :value="period"
                            >
                                {{ period }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.preferred_period" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="notes">Observações (opcional)</Label>
                <Textarea id="notes" v-model="form.notes" rows="3" />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="grid gap-2">
                <Label class="flex items-start gap-2 font-normal">
                    <Checkbox
                        v-model:model-value="form.terms_accepted"
                        class="mt-0.5"
                    />
                    <span>
                        Li e aceito os termos de atendimento e a política de
                        privacidade.
                    </span>
                </Label>
                <InputError :message="form.errors.terms_accepted" />
            </div>

            <!--
                Honeypot: invisível e inalcançável por teclado/leitor de tela
                para uma pessoa real, mas presente no DOM para bots de
                preenchimento automático — ver PublicAppointmentRequestController.
            -->
            <div class="absolute left-[-9999px]" aria-hidden="true">
                <label for="website">Não preencha este campo</label>
                <input
                    id="website"
                    v-model="form.website"
                    type="text"
                    tabindex="-1"
                    autocomplete="off"
                />
            </div>

            <Button type="submit" size="lg" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Criar pré-agendamento
            </Button>
            <p class="text-center text-xs text-muted-foreground">
                O envio não garante reserva do horário — nossa equipe confirmará
                a disponibilidade pelo telefone ou WhatsApp informado.
            </p>
        </form>
    </section>
</template>

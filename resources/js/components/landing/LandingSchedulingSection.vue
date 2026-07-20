<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
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
import type { PublicService } from '@/types/site';

defineProps<{
    services: PublicService[];
}>();

const { selectedServiceId } = useLandingScheduling();

const PERIOD_OPTIONS = ['Manhã', 'Tarde', 'Noite'];

const form = useForm({
    service_id: null as number | null,
    name: '',
    phone: '',
    email: '',
    preferred_period: '',
    notes: '',
    terms_accepted: false,
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

function submit() {
    // form.recentlySuccessful já protege contra clique duplo (botão fica
    // desabilitado enquanto form.processing é true).
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <section id="scheduling" class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
        <div class="mb-8 text-center">
            <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                Agende sua avaliação
            </h2>
            <p class="mt-2 text-muted-foreground">
                Preencha seus dados e entraremos em contato para confirmar o
                melhor horário.
            </p>
        </div>

        <div
            v-if="form.recentlySuccessful"
            class="flex flex-col items-center gap-2 rounded-xl border border-primary/30 bg-primary/5 p-6 text-center"
            role="status"
        >
            <CheckCircle2 class="size-8 text-primary" />
            <p class="font-medium">Solicitação enviada!</p>
            <p class="text-sm text-muted-foreground">
                Recebemos seu pedido de agendamento. Nossa equipe entrará em
                contato em breve.
            </p>
        </div>

        <form
            v-else
            class="grid gap-5 rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8"
            @submit.prevent="submit"
        >
            <div v-if="services.length > 0" class="grid gap-2">
                <Label for="service_id">Serviço de interesse</Label>
                <Select
                    :model-value="form.service_id?.toString() ?? undefined"
                    @update:model-value="
                        (v) => (form.service_id = v ? Number(v) : null)
                    "
                >
                    <SelectTrigger id="service_id" class="w-full">
                        <SelectValue placeholder="Selecione (opcional)" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="service in services"
                            :key="service.id"
                            :value="service.id.toString()"
                        >
                            {{ service.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.service_id" />
            </div>

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
                    <Input
                        id="phone"
                        v-model="form.phone"
                        required
                        autocomplete="tel"
                        placeholder="(00) 00000-0000"
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

            <Button type="submit" size="lg" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Solicitar agendamento
            </Button>
        </form>
    </section>
</template>

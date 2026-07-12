<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import AddressFields from '@/components/organization/AddressFields.vue';
import OpeningHoursFields from '@/components/organization/OpeningHoursFields.vue';
import WizardSteps from '@/components/organization/WizardSteps.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AddressForm, OpeningHourForm } from '@/types/organization';

const props = defineProps<{
    legalEntityTypes: { value: string; label: string }[];
    states: string[];
}>();

const steps = [
    'Organização',
    'Entidade legal',
    'Unidade',
    'Endereço',
    'Horários',
    'Revisão',
];
const currentStep = ref(0);

const form = useForm({
    organization_name: '',
    legal_entity_type: 'individual',
    document: '',
    legal_name: '',
    trade_name: '',
    unit_name: '',
    unit_phone: '',
    unit_whatsapp: '',
    address: {
        postal_code: '',
        street: '',
        number: '',
        complement: '',
        neighborhood: '',
        city: '',
        state: '',
    } as AddressForm,
    opening_hours: [] as OpeningHourForm[],
});

const selectedTypeLabel = computed(
    () =>
        props.legalEntityTypes.find((t) => t.value === form.legal_entity_type)
            ?.label ?? '',
);

function next() {
    if (currentStep.value < steps.length - 1) {
        currentStep.value++;
    }
}

function back() {
    if (currentStep.value > 0) {
        currentStep.value--;
    }
}

function submit() {
    form.post('/onboarding/organization');
}
</script>

<template>
    <Head title="Criar organização" />

    <div class="mx-auto flex max-w-2xl flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">
                Vamos configurar sua organização
            </h1>
            <p class="text-sm text-muted-foreground">
                Essas informações criam sua organização e a primeira unidade
                (matriz).
            </p>
        </div>

        <WizardSteps :steps="steps" :current="currentStep" />

        <Card>
            <CardHeader v-if="currentStep === 5">
                <CardTitle>Revisão</CardTitle>
                <CardDescription
                    >Confira os dados antes de concluir.</CardDescription
                >
            </CardHeader>

            <CardContent class="pt-6">
                <!-- Etapa 1: Organização -->
                <div v-if="currentStep === 0" class="grid gap-2">
                    <Label for="organization-name">Nome da organização</Label>
                    <Input
                        id="organization-name"
                        v-model="form.organization_name"
                        autofocus
                    />
                    <InputError :message="form.errors.organization_name" />
                </div>

                <!-- Etapa 2: Entidade legal -->
                <div v-else-if="currentStep === 1" class="grid gap-4">
                    <div class="grid gap-2">
                        <Label>Tipo</Label>
                        <div class="flex gap-4">
                            <label
                                v-for="type in legalEntityTypes"
                                :key="type.value"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="radio"
                                    name="legal_entity_type"
                                    :value="type.value"
                                    v-model="form.legal_entity_type"
                                />
                                {{ type.label }}
                            </label>
                        </div>
                        <InputError :message="form.errors.legal_entity_type" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="document">{{ selectedTypeLabel }}</Label>
                        <Input
                            id="document"
                            v-model="form.document"
                            placeholder="Somente números ou com máscara"
                        />
                        <InputError :message="form.errors.document" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="legal-name">
                            {{
                                form.legal_entity_type === 'individual'
                                    ? 'Nome completo'
                                    : 'Razão social'
                            }}
                        </Label>
                        <Input id="legal-name" v-model="form.legal_name" />
                        <InputError :message="form.errors.legal_name" />
                    </div>

                    <div
                        v-if="form.legal_entity_type === 'company'"
                        class="grid gap-2"
                    >
                        <Label for="trade-name">Nome fantasia (opcional)</Label>
                        <Input id="trade-name" v-model="form.trade_name" />
                        <InputError :message="form.errors.trade_name" />
                    </div>
                </div>

                <!-- Etapa 3: Unidade matriz -->
                <div v-else-if="currentStep === 2" class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="unit-name">Nome da unidade</Label>
                        <Input
                            id="unit-name"
                            v-model="form.unit_name"
                            placeholder="Ex.: Unidade Centro"
                        />
                        <InputError :message="form.errors.unit_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="unit-phone">Telefone (opcional)</Label>
                        <Input id="unit-phone" v-model="form.unit_phone" />
                        <InputError :message="form.errors.unit_phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="unit-whatsapp">WhatsApp (opcional)</Label>
                        <Input
                            id="unit-whatsapp"
                            v-model="form.unit_whatsapp"
                        />
                        <InputError :message="form.errors.unit_whatsapp" />
                    </div>
                </div>

                <!-- Etapa 4: Endereço -->
                <div v-else-if="currentStep === 3">
                    <AddressFields
                        v-model="form.address"
                        :states="states"
                        :errors="{
                            postal_code: form.errors['address.postal_code'],
                            street: form.errors['address.street'],
                            number: form.errors['address.number'],
                            complement: form.errors['address.complement'],
                            neighborhood: form.errors['address.neighborhood'],
                            city: form.errors['address.city'],
                            state: form.errors['address.state'],
                        }"
                    />
                </div>

                <!-- Etapa 5: Horários -->
                <div v-else-if="currentStep === 4">
                    <OpeningHoursFields
                        v-model="form.opening_hours"
                        :error="form.errors.opening_hours"
                    />
                </div>

                <!-- Etapa 6: Revisão -->
                <div v-else class="grid gap-3 text-sm">
                    <p>
                        <strong>Organização:</strong>
                        {{ form.organization_name }}
                    </p>
                    <p>
                        <strong>{{ selectedTypeLabel }}:</strong>
                        {{ form.document }} — {{ form.legal_name }}
                    </p>
                    <p><strong>Unidade matriz:</strong> {{ form.unit_name }}</p>
                    <p>
                        <strong>Endereço:</strong>
                        {{ form.address.street }}, {{ form.address.number }} —
                        {{ form.address.city }}/{{ form.address.state }}
                    </p>
                    <p>
                        <strong>Horários cadastrados:</strong>
                        {{ form.opening_hours.length }}
                    </p>
                </div>

                <div class="mt-6 flex justify-between">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="currentStep === 0"
                        @click="back"
                        >Voltar</Button
                    >
                    <Button
                        v-if="currentStep < steps.length - 1"
                        type="button"
                        @click="next"
                        >Continuar</Button
                    >
                    <Button
                        v-else
                        type="button"
                        :disabled="form.processing"
                        @click="submit"
                        >Concluir</Button
                    >
                </div>
            </CardContent>
        </Card>
    </div>
</template>

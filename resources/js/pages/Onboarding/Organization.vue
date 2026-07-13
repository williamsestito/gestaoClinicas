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
import { maskCpfCnpj, maskPhone } from '@/lib/masks';
import { store } from '@/routes/onboarding/organization';
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

// Mapeia cada campo do backend para a etapa do assistente onde ele
// aparece — usado para navegar automaticamente até a primeira etapa com
// erro quando a validação do servidor falha.
const fieldStep: Record<string, number> = {
    organization_name: 0,
    legal_entity_type: 1,
    document: 1,
    legal_name: 1,
    trade_name: 1,
    unit_name: 2,
    unit_phone: 2,
    unit_whatsapp: 2,
};

function stepForField(field: string): number {
    if (field in fieldStep) {
        return fieldStep[field];
    }

    if (field.startsWith('address.')) {
        return 3;
    }

    if (field.startsWith('opening_hours')) {
        return 4;
    }

    return 0;
}

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

function currentStepIsValid(): boolean {
    form.clearErrors();

    if (currentStep.value === 0 && !form.organization_name.trim()) {
        form.setError('organization_name', 'Informe o nome da clínica.');

        return false;
    }

    if (currentStep.value === 1) {
        if (!form.document.trim()) {
            form.setError('document', `Informe o ${selectedTypeLabel.value}.`);

            return false;
        }

        if (!form.legal_name.trim()) {
            form.setError(
                'legal_name',
                form.legal_entity_type === 'individual'
                    ? 'Informe o nome completo.'
                    : 'Informe a razão social.',
            );

            return false;
        }
    }

    if (currentStep.value === 2 && !form.unit_name.trim()) {
        form.setError('unit_name', 'Informe o nome da unidade.');

        return false;
    }

    if (currentStep.value === 3) {
        const required: (keyof AddressForm)[] = [
            'postal_code',
            'street',
            'number',
            'neighborhood',
            'city',
            'state',
        ];
        const missing = required.find((field) => !form.address[field]?.trim());

        if (missing) {
            form.setError(
                `address.${missing}`,
                'Preencha o endereço completo.',
            );

            return false;
        }
    }

    return true;
}

function next() {
    if (!currentStepIsValid()) {
        return;
    }

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
    form.post(store().url, {
        onError: (errors) => {
            const fields = Object.keys(errors);

            if (fields.length === 0) {
                return;
            }

            const firstStepWithError = Math.min(
                ...fields.map((field) => stepForField(field)),
            );

            currentStep.value = firstStepWithError;
        },
    });
}

const errorSummary = computed(() =>
    Object.entries(form.errors).filter(([, message]) => Boolean(message)),
);
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
                <div
                    v-if="errorSummary.length > 0"
                    role="alert"
                    class="mb-4 rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
                >
                    <p class="font-medium">
                        Corrija os campos abaixo antes de continuar:
                    </p>
                    <ul class="mt-1 list-inside list-disc">
                        <li v-for="entry in errorSummary" :key="entry[0]">
                            {{ entry[1] }}
                        </li>
                    </ul>
                </div>

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
                            :model-value="form.document"
                            placeholder="Somente números ou com máscara"
                            @update:model-value="
                                (value) =>
                                    (form.document = maskCpfCnpj(
                                        String(value),
                                        form.legal_entity_type as
                                            'individual' | 'company',
                                    ))
                            "
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
                        <Input
                            id="unit-phone"
                            :model-value="form.unit_phone"
                            @update:model-value="
                                (value) =>
                                    (form.unit_phone = maskPhone(String(value)))
                            "
                        />
                        <InputError :message="form.errors.unit_phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="unit-whatsapp">WhatsApp (opcional)</Label>
                        <Input
                            id="unit-whatsapp"
                            :model-value="form.unit_whatsapp"
                            @update:model-value="
                                (value) =>
                                    (form.unit_whatsapp = maskPhone(
                                        String(value),
                                    ))
                            "
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

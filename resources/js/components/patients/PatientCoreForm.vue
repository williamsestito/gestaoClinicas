<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import AddressFields from '@/components/organization/AddressFields.vue';
import EmergencyContactFields from '@/components/patients/EmergencyContactFields.vue';
import type { EmergencyContactForm } from '@/components/patients/EmergencyContactFields.vue';
import ResponsibleFields from '@/components/patients/ResponsibleFields.vue';
import type { ResponsibleForm } from '@/components/patients/ResponsibleFields.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { maskCpf } from '@/lib/masks';
import { store, update } from '@/routes/settings/patients';
import type { AddressForm } from '@/types/organization';

export type EditablePatient = {
    id: string;
    name: string;
    preferred_name: string | null;
    document: string | null;
    birth_date: string;
    phone: string | null;
    whatsapp: string | null;
    email: string | null;
    origin: string | null;
    preferred_unit_id: string | null;
    primary_professional_id: string | null;
    is_minor: boolean;
    has_portal_account?: boolean;
};

const props = withDefaults(
    defineProps<{
        mode: 'create' | 'edit';
        patient?: EditablePatient;
        address?: AddressForm | null;
        states?: string[];
    }>(),
    {
        patient: undefined,
        address: null,
        states: () => [],
    },
);

const emit = defineEmits<{
    success: [];
    cancel: [];
}>();

function emptyAddress(): AddressForm {
    return {
        postal_code: '',
        street: '',
        number: '',
        complement: '',
        neighborhood: '',
        city: '',
        state: '',
    };
}

const createForm = useForm({
    name: '',
    preferred_name: '',
    document: '',
    birth_date: '',
    phone: '',
    whatsapp: '',
    email: '',
    origin: '',
    preferred_unit_id: '',
    primary_professional_id: '',
    address: emptyAddress(),
    emergency_contacts: [] as EmergencyContactForm[],
    responsibles: [] as ResponsibleForm[],
});

const editForm = useForm({
    name: props.patient?.name ?? '',
    preferred_name: props.patient?.preferred_name ?? '',
    document: props.patient?.document ?? '',
    birth_date: props.patient?.birth_date ?? '',
    phone: props.patient?.phone ?? '',
    whatsapp: props.patient?.whatsapp ?? '',
    email: props.patient?.email ?? '',
    origin: props.patient?.origin ?? '',
    preferred_unit_id: props.patient?.preferred_unit_id ?? '',
    primary_professional_id: props.patient?.primary_professional_id ?? '',
    address: props.address ?? emptyAddress(),
});

const form = computed(() => (props.mode === 'create' ? createForm : editForm));

const isMinor = computed(() => {
    if (props.mode === 'edit') {
        return props.patient?.is_minor ?? false;
    }

    if (!createForm.birth_date) {
        return false;
    }

    const birth = new Date(createForm.birth_date);
    const age = Math.floor(
        (Date.now() - birth.getTime()) / (365.25 * 24 * 60 * 60 * 1000),
    );

    return age < 18;
});

function submit() {
    if (props.mode === 'create') {
        createForm.post(store().url, { onSuccess: () => emit('success') });

        return;
    }

    if (props.patient) {
        editForm.put(update(props.patient.id).url, {
            onSuccess: () => emit('success'),
        });
    }
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-4">
            <div>
                <h3 class="text-sm font-medium">Identificação</h3>
                <p class="text-sm text-muted-foreground">
                    Dados básicos do paciente.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="patient-name">Nome</Label>
                    <Input id="patient-name" v-model="form.name" autofocus />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="patient-preferred-name">
                        Nome de preferência (opcional)
                    </Label>
                    <Input
                        id="patient-preferred-name"
                        v-model="form.preferred_name"
                    />
                    <InputError :message="form.errors.preferred_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="patient-document">CPF (opcional)</Label>
                    <Input
                        id="patient-document"
                        :model-value="form.document"
                        @update:model-value="
                            (value) => (form.document = maskCpf(String(value)))
                        "
                    />
                    <InputError :message="form.errors.document" />
                </div>
                <div class="grid gap-2">
                    <Label for="patient-birth-date">Data de nascimento</Label>
                    <Input
                        id="patient-birth-date"
                        v-model="form.birth_date"
                        type="date"
                    />
                    <InputError :message="form.errors.birth_date" />
                    <p
                        v-if="isMinor"
                        class="text-sm text-amber-600 dark:text-amber-500"
                    >
                        Paciente menor de 18 anos — é necessário informar ao
                        menos um responsável legal.
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="patient-phone">Telefone (opcional)</Label>
                    <PhoneInput id="patient-phone" v-model="form.phone" />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-2">
                    <Label for="patient-whatsapp">WhatsApp (opcional)</Label>
                    <PhoneInput id="patient-whatsapp" v-model="form.whatsapp" />
                    <InputError :message="form.errors.whatsapp" />
                </div>
                <div class="grid gap-2">
                    <Label for="patient-email">E-mail (opcional)</Label>
                    <Input
                        id="patient-email"
                        v-model="form.email"
                        type="email"
                    />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="patient-origin">Origem (opcional)</Label>
                    <Input
                        id="patient-origin"
                        v-model="form.origin"
                        placeholder="indicação, site, redes sociais…"
                    />
                    <InputError :message="form.errors.origin" />
                </div>
            </div>
        </div>

        <Separator />

        <div class="grid gap-4">
            <div>
                <h3 class="text-sm font-medium">Endereço (opcional)</h3>
                <p class="text-sm text-muted-foreground">
                    Informe o CEP para preencher automaticamente. Se preencher,
                    rua, número, bairro, cidade e UF são obrigatórios.
                </p>
            </div>
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

        <template v-if="mode === 'create'">
            <Separator />

            <div class="grid gap-4">
                <div>
                    <h3 class="text-sm font-medium">Contato de emergência</h3>
                    <p class="text-sm text-muted-foreground">
                        Obrigatório — ao menos um contato de emergência.
                    </p>
                </div>
                <EmergencyContactFields
                    v-model="createForm.emergency_contacts"
                    :errors="createForm.errors"
                />
            </div>

            <Separator />

            <div class="grid gap-4">
                <div>
                    <h3 class="text-sm font-medium">Responsáveis</h3>
                    <p class="text-sm text-muted-foreground">
                        Obrigatório apenas para pacientes menores de 18 anos.
                    </p>
                </div>
                <ResponsibleFields
                    v-model="createForm.responsibles"
                    :errors="createForm.errors"
                    :required="isMinor"
                />
            </div>
        </template>

        <div class="flex items-center justify-end gap-2">
            <Button
                type="button"
                variant="secondary"
                :disabled="form.processing"
                @click="emit('cancel')"
            >
                Cancelar
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{
                    mode === 'create'
                        ? 'Cadastrar paciente'
                        : 'Salvar alterações'
                }}
            </Button>
        </div>
    </form>
</template>

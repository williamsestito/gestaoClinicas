<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import AddressFields from '@/components/organization/AddressFields.vue';
import OpeningHoursFields from '@/components/organization/OpeningHoursFields.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { maskPostalCode } from '@/lib/masks';
import { store, update } from '@/routes/settings/units';
import type { AddressForm, OpeningHourForm, Unit } from '@/types/organization';

export type EditableUnit = Unit & {
    address: {
        postal_code: string;
        street: string;
        number: string;
        complement: string | null;
        neighborhood: string;
        city: string;
        state: string;
    } | null;
    opening_hours: {
        day_of_week: number;
        opens_at: string;
        closes_at: string;
    }[];
};

const props = withDefaults(
    defineProps<{
        mode: 'create' | 'edit';
        unit?: EditableUnit;
        states?: string[];
    }>(),
    {
        unit: undefined,
        states: () => [],
    },
);

const emit = defineEmits<{
    success: [];
    cancel: [];
}>();

const createForm = useForm({
    name: '',
    code: '',
    phone: '',
    whatsapp: '',
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

const editForm = useForm({
    name: props.unit?.name ?? '',
    phone: props.unit?.phone ?? '',
    whatsapp: props.unit?.whatsapp ?? '',
    email: props.unit?.email ?? '',
    timezone: props.unit?.timezone ?? '',
    address: {
        postal_code: maskPostalCode(props.unit?.address?.postal_code ?? ''),
        street: props.unit?.address?.street ?? '',
        number: props.unit?.address?.number ?? '',
        complement: props.unit?.address?.complement ?? '',
        neighborhood: props.unit?.address?.neighborhood ?? '',
        city: props.unit?.address?.city ?? '',
        state: props.unit?.address?.state ?? '',
    } as AddressForm,
    opening_hours: (props.unit?.opening_hours ?? []).map((hour) => ({
        day_of_week: hour.day_of_week,
        opens_at: hour.opens_at,
        closes_at: hour.closes_at,
    })) as OpeningHourForm[],
});

const form = computed(() => (props.mode === 'create' ? createForm : editForm));

function submit() {
    if (props.mode === 'create') {
        createForm.post(store().url, { onSuccess: () => emit('success') });

        return;
    }

    if (props.unit) {
        editForm.put(update(props.unit.id).url, {
            onSuccess: () => emit('success'),
        });
    }
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-4">
            <div>
                <h3 class="text-sm font-medium">Informações da unidade</h3>
                <p class="text-sm text-muted-foreground">
                    Nome, código e contatos usados para identificar a unidade.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="unit-name">Nome</Label>
                    <Input id="unit-name" v-model="form.name" autofocus />
                    <InputError :message="form.errors.name" />
                </div>

                <div v-if="mode === 'create'" class="grid gap-2">
                    <Label for="unit-code"
                        >Código (opcional — gerado automaticamente)</Label
                    >
                    <Input id="unit-code" v-model="createForm.code" />
                    <InputError :message="createForm.errors.code" />
                </div>
                <p
                    v-else-if="unit"
                    class="self-end text-sm text-muted-foreground sm:pb-2"
                >
                    Código: {{ unit.code }}
                </p>

                <div class="grid gap-2">
                    <Label for="unit-phone">Telefone (opcional)</Label>
                    <PhoneInput id="unit-phone" v-model="form.phone" />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-2">
                    <Label for="unit-whatsapp">WhatsApp (opcional)</Label>
                    <PhoneInput id="unit-whatsapp" v-model="form.whatsapp" />
                    <InputError :message="form.errors.whatsapp" />
                </div>

                <template v-if="mode === 'edit'">
                    <div class="grid gap-2">
                        <Label for="unit-email">E-mail (opcional)</Label>
                        <Input
                            id="unit-email"
                            v-model="editForm.email"
                            type="email"
                        />
                        <InputError :message="editForm.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="unit-timezone">Fuso horário</Label>
                        <Input id="unit-timezone" v-model="editForm.timezone" />
                        <InputError :message="editForm.errors.timezone" />
                    </div>
                </template>
            </div>
        </div>

        <Separator />

        <div class="grid gap-4">
            <div>
                <h3 class="text-sm font-medium">Endereço</h3>
                <p class="text-sm text-muted-foreground">
                    Informe o CEP para preencher automaticamente; ajuste o que
                    for necessário.
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

        <Separator />

        <div class="grid gap-4">
            <div>
                <h3 class="text-sm font-medium">Horários de funcionamento</h3>
                <p class="text-sm text-muted-foreground">
                    Opcional — adicione um ou mais intervalos por dia da semana.
                </p>
            </div>
            <OpeningHoursFields
                v-model="form.opening_hours"
                :error="form.errors.opening_hours"
            />
        </div>

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
                {{ mode === 'create' ? 'Criar unidade' : 'Salvar alterações' }}
            </Button>
        </div>
    </form>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import AddressFields from '@/components/organization/AddressFields.vue';
import OpeningHoursFields from '@/components/organization/OpeningHoursFields.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { store, update } from '@/routes/settings/units';
import { WEEKDAYS } from '@/types/organization';
import type { AddressForm, OpeningHourForm, Unit } from '@/types/organization';

export type EditableUnit = Unit & {
    address: {
        street: string;
        number: string;
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

// O backend só aceita endereço e horários na criação (UpdateUnitRequest não
// tem esses campos) — em edição eles são somente leitura, propositalmente.
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

function dayLabel(day: number): string {
    return WEEKDAYS.find((w) => w.value === day)?.label ?? String(day);
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-4">
            <div>
                <h3 class="text-sm font-medium">Informações da unidade</h3>
                <p class="text-muted-foreground text-sm">
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
                    class="text-muted-foreground self-end text-sm sm:pb-2"
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

        <div v-if="mode === 'create'" class="grid gap-4">
            <div>
                <h3 class="text-sm font-medium">Endereço</h3>
                <p class="text-muted-foreground text-sm">
                    Informe o CEP para preencher automaticamente; ajuste o que
                    for necessário.
                </p>
            </div>
            <AddressFields
                v-model="createForm.address"
                :states="states"
                :errors="{
                    postal_code: createForm.errors['address.postal_code'],
                    street: createForm.errors['address.street'],
                    number: createForm.errors['address.number'],
                    complement: createForm.errors['address.complement'],
                    neighborhood: createForm.errors['address.neighborhood'],
                    city: createForm.errors['address.city'],
                    state: createForm.errors['address.state'],
                }"
            />
        </div>
        <div v-else-if="unit" class="grid gap-4">
            <h3 class="text-sm font-medium">Endereço</h3>
            <Card v-if="unit.address">
                <CardContent class="text-muted-foreground py-4 text-sm">
                    {{ unit.address.street }}, {{ unit.address.number }} —
                    {{ unit.address.city }}/{{ unit.address.state }}
                </CardContent>
            </Card>
            <p v-else class="text-muted-foreground text-sm">
                Endereço não cadastrado.
            </p>
            <p class="text-muted-foreground text-xs">
                Endereço não é editável por aqui nesta etapa.
            </p>
        </div>

        <Separator />

        <div v-if="mode === 'create'" class="grid gap-4">
            <div>
                <h3 class="text-sm font-medium">Horários de funcionamento</h3>
                <p class="text-muted-foreground text-sm">
                    Opcional — adicione um ou mais intervalos por dia da semana.
                </p>
            </div>
            <OpeningHoursFields
                v-model="createForm.opening_hours"
                :error="createForm.errors.opening_hours"
            />
        </div>
        <div v-else-if="unit" class="grid gap-4">
            <h3 class="text-sm font-medium">Horários de funcionamento</h3>
            <Card v-if="unit.opening_hours.length">
                <CardContent
                    class="text-muted-foreground grid gap-1 py-4 text-sm"
                >
                    <div
                        v-for="(hour, index) in unit.opening_hours"
                        :key="index"
                    >
                        {{ dayLabel(hour.day_of_week) }}: {{ hour.opens_at }} às
                        {{ hour.closes_at }}
                    </div>
                </CardContent>
            </Card>
            <p v-else class="text-muted-foreground text-sm">
                Nenhum horário de funcionamento cadastrado ainda.
            </p>
            <p class="text-muted-foreground text-xs">
                Horários não são editáveis por aqui nesta etapa.
            </p>
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

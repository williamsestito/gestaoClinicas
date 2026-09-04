<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import AddressFields from '@/components/organization/AddressFields.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { maskCpfCnpj } from '@/lib/masks';
import { store, update } from '@/routes/settings/legal-entities';
import type { AddressForm, LegalEntity } from '@/types/organization';

export type EditableLegalEntity = LegalEntity & {
    address: {
        street: string;
        number: string;
        city: string;
        state: string;
    } | null;
};

const props = withDefaults(
    defineProps<{
        mode: 'create' | 'edit';
        legalEntity?: EditableLegalEntity;
        legalEntityTypes?: { value: string; label: string }[];
        states?: string[];
    }>(),
    {
        legalEntity: undefined,
        legalEntityTypes: () => [],
        states: () => [],
    },
);

const emit = defineEmits<{ success: []; cancel: [] }>();

// O backend só aceita tipo/documento/endereço na criação — em edição são
// somente leitura, propositalmente (mesma regra de Unidades).
const createForm = useForm({
    type: 'individual',
    document: '',
    legal_name: '',
    trade_name: '',
    state_registration: '',
    municipal_registration: '',
    email: '',
    phone: '',
    address: {
        postal_code: '',
        street: '',
        number: '',
        complement: '',
        neighborhood: '',
        city: '',
        state: '',
    } as AddressForm,
});

const editForm = useForm({
    legal_name: props.legalEntity?.legal_name ?? '',
    trade_name: props.legalEntity?.trade_name ?? '',
    state_registration: props.legalEntity?.state_registration ?? '',
    municipal_registration: props.legalEntity?.municipal_registration ?? '',
    email: props.legalEntity?.email ?? '',
    phone: props.legalEntity?.phone ?? '',
});

const form = computed(() => (props.mode === 'create' ? createForm : editForm));

const entityType = computed(() => props.legalEntity?.type ?? createForm.type);

const selectedTypeLabel = computed(
    () =>
        props.legalEntityTypes.find((t) => t.value === createForm.type)
            ?.label ?? '',
);

function submit() {
    if (props.mode === 'create') {
        createForm.post(store().url, { onSuccess: () => emit('success') });

        return;
    }

    if (props.legalEntity) {
        editForm.put(update(props.legalEntity.id).url, {
            onSuccess: () => emit('success'),
        });
    }
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-4">
            <div v-if="mode === 'create'" class="grid gap-2">
                <Label>Tipo</Label>
                <div class="flex gap-4">
                    <label
                        v-for="type in legalEntityTypes"
                        :key="type.value"
                        class="flex items-center gap-2 text-sm"
                    >
                        <input
                            v-model="createForm.type"
                            type="radio"
                            name="type"
                            :value="type.value"
                        />
                        {{ type.label }}
                    </label>
                </div>
                <InputError :message="createForm.errors.type" />
            </div>

            <div v-if="mode === 'create'" class="grid gap-2">
                <Label for="entity-document">{{ selectedTypeLabel }}</Label>
                <Input
                    id="entity-document"
                    :model-value="createForm.document"
                    placeholder="Somente números ou com máscara"
                    @update:model-value="
                        (value) =>
                            (createForm.document = maskCpfCnpj(
                                String(value),
                                createForm.type as 'individual' | 'company',
                            ))
                    "
                />
                <InputError :message="createForm.errors.document" />
            </div>
            <p v-else-if="legalEntity" class="text-muted-foreground text-sm">
                {{ legalEntity.document }}
            </p>

            <div class="grid gap-2">
                <Label for="entity-legal-name">
                    {{
                        entityType === 'individual'
                            ? 'Nome completo'
                            : 'Razão social'
                    }}
                </Label>
                <Input
                    id="entity-legal-name"
                    v-model="form.legal_name"
                    autofocus
                />
                <InputError :message="form.errors.legal_name" />
            </div>

            <div v-if="entityType === 'company'" class="grid gap-2">
                <Label for="entity-trade-name">Nome fantasia (opcional)</Label>
                <Input id="entity-trade-name" v-model="form.trade_name" />
                <InputError :message="form.errors.trade_name" />
            </div>

            <div v-if="entityType === 'company'" class="grid gap-2">
                <Label for="entity-state-registration">
                    Inscrição estadual (opcional)
                </Label>
                <Input
                    id="entity-state-registration"
                    v-model="form.state_registration"
                />
                <InputError :message="form.errors.state_registration" />
            </div>

            <div v-if="entityType === 'company'" class="grid gap-2">
                <Label for="entity-municipal-registration">
                    Inscrição municipal (opcional)
                </Label>
                <Input
                    id="entity-municipal-registration"
                    v-model="form.municipal_registration"
                />
                <InputError :message="form.errors.municipal_registration" />
            </div>

            <div class="grid gap-2">
                <Label for="entity-email">E-mail (opcional)</Label>
                <Input id="entity-email" v-model="form.email" type="email" />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="entity-phone">Telefone (opcional)</Label>
                <PhoneInput id="entity-phone" v-model="form.phone" />
                <InputError :message="form.errors.phone" />
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
        <div v-else-if="legalEntity" class="grid gap-4">
            <h3 class="text-sm font-medium">Endereço</h3>
            <Card v-if="legalEntity.address">
                <CardContent class="text-muted-foreground py-4 text-sm">
                    {{ legalEntity.address.street }},
                    {{ legalEntity.address.number }} —
                    {{ legalEntity.address.city }}/{{
                        legalEntity.address.state
                    }}
                </CardContent>
            </Card>
            <p v-else class="text-muted-foreground text-sm">
                Endereço não cadastrado.
            </p>
            <p class="text-muted-foreground text-xs">
                Endereço não é editável por aqui nesta etapa.
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
                {{
                    mode === 'create'
                        ? 'Criar entidade legal'
                        : 'Salvar alterações'
                }}
            </Button>
        </div>
    </form>
</template>

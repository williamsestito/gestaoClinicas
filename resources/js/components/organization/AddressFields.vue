<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCepLookup } from '@/composables/useCepLookup';
import type { AddressForm } from '@/types/organization';

const address = defineModel<AddressForm>({ required: true });

defineProps<{
    states: string[];
    errors?: Partial<Record<keyof AddressForm, string>>;
}>();

const { lookup, isLoading, notFound } = useCepLookup();

async function onPostalCodeBlur() {
    const result = await lookup(address.value.postal_code);

    if (!result) {
        return;
    }

    address.value = {
        ...address.value,
        postal_code: result.postal_code,
        street: result.street || address.value.street,
        neighborhood: result.neighborhood || address.value.neighborhood,
        city: result.city || address.value.city,
        state: result.state || address.value.state,
    };
}
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-2 sm:max-w-[200px]">
            <Label for="address-postal-code">CEP</Label>
            <Input
                id="address-postal-code"
                v-model="address.postal_code"
                name="address[postal_code]"
                inputmode="numeric"
                maxlength="9"
                placeholder="00000-000"
                @blur="onPostalCodeBlur"
            />
            <p v-if="isLoading" class="text-sm text-muted-foreground">
                Consultando CEP…
            </p>
            <p v-else-if="notFound" class="text-sm text-muted-foreground">
                CEP não encontrado — preencha o endereço manualmente.
            </p>
            <InputError :message="errors?.postal_code" />
        </div>

        <div class="grid gap-4 sm:grid-cols-[1fr_120px]">
            <div class="grid gap-2">
                <Label for="address-street">Rua</Label>
                <Input
                    id="address-street"
                    v-model="address.street"
                    name="address[street]"
                />
                <InputError :message="errors?.street" />
            </div>
            <div class="grid gap-2">
                <Label for="address-number">Número</Label>
                <Input
                    id="address-number"
                    v-model="address.number"
                    name="address[number]"
                />
                <InputError :message="errors?.number" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="address-complement">Complemento (opcional)</Label>
            <Input
                id="address-complement"
                v-model="address.complement"
                name="address[complement]"
            />
            <InputError :message="errors?.complement" />
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="grid gap-2">
                <Label for="address-neighborhood">Bairro</Label>
                <Input
                    id="address-neighborhood"
                    v-model="address.neighborhood"
                    name="address[neighborhood]"
                />
                <InputError :message="errors?.neighborhood" />
            </div>
            <div class="grid gap-2">
                <Label for="address-city">Cidade</Label>
                <Input
                    id="address-city"
                    v-model="address.city"
                    name="address[city]"
                />
                <InputError :message="errors?.city" />
            </div>
            <div class="grid gap-2">
                <Label for="address-state">UF</Label>
                <select
                    id="address-state"
                    v-model="address.state"
                    name="address[state]"
                    class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="" disabled>UF</option>
                    <option v-for="state in states" :key="state" :value="state">
                        {{ state }}
                    </option>
                </select>
                <InputError :message="errors?.state" />
            </div>
        </div>
    </div>
</template>

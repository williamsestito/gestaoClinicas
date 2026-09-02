<script setup lang="ts">
import { Search } from '@lucide/vue';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCepLookup } from '@/composables/useCepLookup';
import { maskPostalCode } from '@/lib/masks';
import type { AddressForm } from '@/types/organization';

const address = defineModel<AddressForm>({ required: true });

defineProps<{
    states: string[];
    errors?: Partial<Record<keyof AddressForm, string>>;
}>();

const { lookup, status } = useCepLookup();

// `defineModel` só notifica o pai (`update:modelValue`) quando `.value` é
// reatribuído por inteiro — nunca quando uma propriedade dele é mutada
// (`address.value.x = y`). `v-model="address.x"` compila exatamente para
// essa mutação por propriedade, então nunca chegava a notificar o pai.
// Isso passava despercebido para rua/bairro/cidade/UF só porque
// `fillFromCep()` reatribui o objeto inteiro como efeito colateral da
// busca por CEP — número e complemento nunca entram nesse retorno, então
// nunca tinham nenhum outro gatilho de sincronização.
function updateField<K extends keyof AddressForm>(
    field: K,
    value: AddressForm[K],
) {
    address.value = { ...address.value, [field]: value };
}

async function fillFromCep(options: { force?: boolean } = {}) {
    const result = await lookup(address.value.postal_code, options);

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

// Consulta automaticamente assim que o CEP tiver 8 dígitos — sem repetir a
// requisição a cada tecla, já que o gatilho só dispara nesse limiar exato.
watch(
    () => address.value.postal_code,
    (postalCode) => {
        if (postalCode.replace(/\D/g, '').length === 8) {
            fillFromCep();
        }
    },
);
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-2 sm:max-w-70">
            <Label for="address-postal-code">CEP</Label>
            <div class="flex gap-2">
                <Input
                    id="address-postal-code"
                    :model-value="address.postal_code"
                    name="address[postal_code]"
                    inputmode="numeric"
                    maxlength="9"
                    placeholder="00000-000"
                    class="flex-1"
                    @update:model-value="
                        (value) =>
                            updateField(
                                'postal_code',
                                maskPostalCode(String(value)),
                            )
                    "
                    @blur="fillFromCep()"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    :disabled="status === 'loading'"
                    aria-label="Buscar endereço pelo CEP"
                    @click="fillFromCep({ force: true })"
                >
                    <Search class="size-4" />
                </Button>
            </div>
            <p
                v-if="status === 'loading'"
                class="text-sm text-muted-foreground"
            >
                Buscando endereço…
            </p>
            <p
                v-else-if="status === 'success'"
                class="text-sm text-muted-foreground"
            >
                Endereço localizado. Confira os dados antes de continuar.
            </p>
            <p
                v-else-if="status === 'not-found'"
                class="text-sm text-muted-foreground"
            >
                Não foi possível localizar esse CEP automaticamente. Preencha o
                endereço manualmente.
            </p>
            <p
                v-else-if="status === 'error'"
                class="text-sm text-muted-foreground"
            >
                Não foi possível consultar o CEP neste momento. Você pode
                preencher o endereço manualmente.
            </p>
            <InputError :message="errors?.postal_code" />
        </div>

        <div class="grid gap-4 sm:grid-cols-[1fr_120px]">
            <div class="grid gap-2">
                <Label for="address-street">Rua</Label>
                <Input
                    id="address-street"
                    :model-value="address.street"
                    name="address[street]"
                    @update:model-value="
                        (value) => updateField('street', String(value))
                    "
                />
                <InputError :message="errors?.street" />
            </div>
            <div class="grid gap-2">
                <Label for="address-number">Número</Label>
                <Input
                    id="address-number"
                    :model-value="address.number"
                    name="address[number]"
                    @update:model-value="
                        (value) => updateField('number', String(value))
                    "
                />
                <InputError :message="errors?.number" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="address-complement">Complemento (opcional)</Label>
            <Input
                id="address-complement"
                :model-value="address.complement"
                name="address[complement]"
                @update:model-value="
                    (value) => updateField('complement', String(value))
                "
            />
            <InputError :message="errors?.complement" />
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="grid gap-2">
                <Label for="address-neighborhood">Bairro</Label>
                <Input
                    id="address-neighborhood"
                    :model-value="address.neighborhood"
                    name="address[neighborhood]"
                    @update:model-value="
                        (value) => updateField('neighborhood', String(value))
                    "
                />
                <InputError :message="errors?.neighborhood" />
            </div>
            <div class="grid gap-2">
                <Label for="address-city">Cidade</Label>
                <Input
                    id="address-city"
                    :model-value="address.city"
                    name="address[city]"
                    @update:model-value="
                        (value) => updateField('city', String(value))
                    "
                />
                <InputError :message="errors?.city" />
            </div>
            <div class="grid gap-2">
                <Label for="address-state">UF</Label>
                <select
                    id="address-state"
                    :value="address.state"
                    name="address[state]"
                    @change="
                        (event) =>
                            updateField(
                                'state',
                                (event.target as HTMLSelectElement).value,
                            )
                    "
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

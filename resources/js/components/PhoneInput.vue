<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { maskPhone } from '@/lib/masks';

/**
 * Campo de telefone/WhatsApp com máscara aplicada enquanto a pessoa digita
 * (ex.: "47996961511" vira "(47) 99696-1511"), limitado aos 11 dígitos de
 * um celular — usado em todo formulário com telefone/WhatsApp, na landing
 * e no sistema, para nunca duplicar essa lógica em cada tela. A máscara é
 * só apresentação; o backend é sempre a fonte de verdade da validação.
 */
const model = defineModel<string>({ default: '' });

function onUpdate(value: string | number) {
    model.value = maskPhone(String(value));
}
</script>

<template>
    <Input
        :model-value="model"
        type="tel"
        inputmode="numeric"
        placeholder="(00) 00000-0000"
        maxlength="15"
        @update:model-value="onUpdate"
    />
</template>

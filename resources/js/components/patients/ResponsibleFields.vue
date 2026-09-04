<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { maskCpf } from '@/lib/masks';

export type ResponsibleForm = {
    name: string;
    document: string;
    phone: string;
    relationship: string;
    is_legal_guardian: boolean;
    is_financial_responsible: boolean;
    is_authorized_representative: boolean;
};

const responsibles = defineModel<ResponsibleForm[]>({ required: true });

defineProps<{
    errors?: Record<string, string>;
    required?: boolean;
}>();

function addRow() {
    responsibles.value = [
        ...responsibles.value,
        {
            name: '',
            document: '',
            phone: '',
            relationship: '',
            is_legal_guardian: true,
            is_financial_responsible: false,
            is_authorized_representative: false,
        },
    ];
}

function removeRow(index: number) {
    responsibles.value = responsibles.value.filter((_, i) => i !== index);
}
</script>

<template>
    <div class="grid gap-4">
        <p v-if="required" class="text-muted-foreground text-sm">
            Paciente menor de 18 anos — informe ao menos um responsável legal.
        </p>
        <div
            v-if="responsibles.length === 0"
            class="text-muted-foreground text-sm"
        >
            Nenhum responsável adicionado ainda.
        </div>

        <div
            v-for="(responsible, index) in responsibles"
            :key="index"
            class="grid gap-3 rounded-lg border p-4"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label :for="`responsible-name-${index}`">Nome</Label>
                    <Input
                        :id="`responsible-name-${index}`"
                        v-model="responsible.name"
                    />
                    <InputError
                        :message="errors?.[`responsibles.${index}.name`]"
                    />
                </div>
                <div class="grid gap-2">
                    <Label :for="`responsible-relationship-${index}`">
                        Relação com o paciente
                    </Label>
                    <Input
                        :id="`responsible-relationship-${index}`"
                        v-model="responsible.relationship"
                        placeholder="mãe, pai, tutor legal…"
                    />
                    <InputError
                        :message="
                            errors?.[`responsibles.${index}.relationship`]
                        "
                    />
                </div>
                <div class="grid gap-2">
                    <Label :for="`responsible-document-${index}`">
                        CPF (opcional)
                    </Label>
                    <Input
                        :id="`responsible-document-${index}`"
                        :model-value="responsible.document"
                        @update:model-value="
                            (value) =>
                                (responsible.document = maskCpf(String(value)))
                        "
                    />
                    <InputError
                        :message="errors?.[`responsibles.${index}.document`]"
                    />
                </div>
                <div class="grid gap-2">
                    <Label :for="`responsible-phone-${index}`">Telefone</Label>
                    <PhoneInput
                        :id="`responsible-phone-${index}`"
                        v-model="responsible.phone"
                    />
                    <InputError
                        :message="errors?.[`responsibles.${index}.phone`]"
                    />
                </div>
            </div>

            <div class="flex flex-wrap gap-4">
                <Label class="flex items-center gap-2 font-normal">
                    <Checkbox
                        v-model:model-value="responsible.is_legal_guardian"
                    />
                    Responsável legal
                </Label>
                <Label class="flex items-center gap-2 font-normal">
                    <Checkbox
                        v-model:model-value="
                            responsible.is_financial_responsible
                        "
                    />
                    Responsável financeiro
                </Label>
                <Label class="flex items-center gap-2 font-normal">
                    <Checkbox
                        v-model:model-value="
                            responsible.is_authorized_representative
                        "
                    />
                    Representante autorizado
                </Label>
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                class="w-fit"
                @click="removeRow(index)"
            >
                Remover responsável
            </Button>
        </div>

        <InputError :message="errors?.responsibles" />

        <Button type="button" variant="outline" class="w-fit" @click="addRow">
            Adicionar responsável
        </Button>
    </div>
</template>

<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type EmergencyContactForm = {
    name: string;
    relationship: string;
    phone_primary: string;
    phone_secondary: string;
};

const contacts = defineModel<EmergencyContactForm[]>({ required: true });

defineProps<{
    errors?: Record<string, string>;
}>();

function addRow() {
    contacts.value = [
        ...contacts.value,
        { name: '', relationship: '', phone_primary: '', phone_secondary: '' },
    ];
}

function removeRow(index: number) {
    contacts.value = contacts.value.filter((_, i) => i !== index);
}
</script>

<template>
    <div class="grid gap-4">
        <div v-if="contacts.length === 0" class="text-sm text-muted-foreground">
            Nenhum contato de emergência adicionado ainda.
        </div>

        <div
            v-for="(contact, index) in contacts"
            :key="index"
            class="grid gap-3 rounded-lg border p-4"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label :for="`emergency-name-${index}`">Nome</Label>
                    <Input
                        :id="`emergency-name-${index}`"
                        v-model="contact.name"
                    />
                    <InputError
                        :message="errors?.[`emergency_contacts.${index}.name`]"
                    />
                </div>
                <div class="grid gap-2">
                    <Label :for="`emergency-relationship-${index}`">
                        Relação com o paciente
                    </Label>
                    <Input
                        :id="`emergency-relationship-${index}`"
                        v-model="contact.relationship"
                        placeholder="mãe, cônjuge, amigo(a)…"
                    />
                    <InputError
                        :message="
                            errors?.[`emergency_contacts.${index}.relationship`]
                        "
                    />
                </div>
                <div class="grid gap-2">
                    <Label :for="`emergency-phone-${index}`"
                        >Telefone principal</Label
                    >
                    <PhoneInput
                        :id="`emergency-phone-${index}`"
                        v-model="contact.phone_primary"
                    />
                    <InputError
                        :message="
                            errors?.[
                                `emergency_contacts.${index}.phone_primary`
                            ]
                        "
                    />
                </div>
                <div class="grid gap-2">
                    <Label :for="`emergency-phone-alt-${index}`">
                        Telefone alternativo (opcional)
                    </Label>
                    <PhoneInput
                        :id="`emergency-phone-alt-${index}`"
                        v-model="contact.phone_secondary"
                    />
                </div>
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                class="w-fit"
                @click="removeRow(index)"
            >
                Remover contato
            </Button>
        </div>

        <InputError :message="errors?.emergency_contacts" />

        <Button type="button" variant="outline" class="w-fit" @click="addRow">
            Adicionar contato de emergência
        </Button>
    </div>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/settings/professionals/registrations';

export type EditableRegistration = {
    id: string;
    council: string;
    registration_type: string | null;
    state: string | null;
    issued_at: string | null;
    expires_at: string | null;
};

const props = withDefaults(
    defineProps<{
        mode: 'create' | 'edit';
        professionalId: string;
        registration?: EditableRegistration;
    }>(),
    {
        registration: undefined,
    },
);

const emit = defineEmits<{
    success: [];
    cancel: [];
}>();

const form = useForm({
    council: props.registration?.council ?? '',
    registration_type: props.registration?.registration_type ?? '',
    registration_number: '',
    state: props.registration?.state ?? '',
    issued_at: props.registration?.issued_at ?? '',
    expires_at: props.registration?.expires_at ?? '',
});

function submit() {
    if (props.mode === 'create') {
        form.post(store(props.professionalId).url, {
            preserveScroll: true,
            onSuccess: () => emit('success'),
        });

        return;
    }

    if (props.registration) {
        form.put(update([props.professionalId, props.registration.id]).url, {
            preserveScroll: true,
            onSuccess: () => emit('success'),
        });
    }
}
</script>

<template>
    <form class="grid gap-4" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="registration-council">Conselho ou órgão</Label>
            <Input
                id="registration-council"
                v-model="form.council"
                placeholder="Ex.: CRM, CRO, COREN"
                autofocus
            />
            <InputError :message="form.errors.council" />
        </div>

        <div class="grid gap-2">
            <Label for="registration-type">Tipo de registro (opcional)</Label>
            <Input id="registration-type" v-model="form.registration_type" />
            <InputError :message="form.errors.registration_type" />
        </div>

        <div class="grid gap-2">
            <Label for="registration-number">Número</Label>
            <Input
                id="registration-number"
                v-model="form.registration_number"
                :placeholder="
                    mode === 'edit'
                        ? 'Deixe em branco para manter o número atual'
                        : undefined
                "
            />
            <InputError :message="form.errors.registration_number" />
        </div>

        <div class="grid gap-2">
            <Label for="registration-state"
                >UF (opcional para órgãos nacionais)</Label
            >
            <Input
                id="registration-state"
                v-model="form.state"
                maxlength="2"
                class="max-w-24 uppercase"
            />
            <InputError :message="form.errors.state" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="grid gap-2">
                <Label for="registration-issued">Emissão (opcional)</Label>
                <Input
                    id="registration-issued"
                    v-model="form.issued_at"
                    type="date"
                />
                <InputError :message="form.errors.issued_at" />
            </div>
            <div class="grid gap-2">
                <Label for="registration-expires">Validade (opcional)</Label>
                <Input
                    id="registration-expires"
                    v-model="form.expires_at"
                    type="date"
                />
                <InputError :message="form.errors.expires_at" />
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
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
                        ? 'Cadastrar registro'
                        : 'Salvar alterações'
                }}
            </Button>
        </div>
    </form>
</template>

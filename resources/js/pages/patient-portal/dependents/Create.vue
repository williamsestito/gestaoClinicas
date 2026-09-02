<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { maskCpf } from '@/lib/masks';
import patientPortal from '@/routes/patient-portal';

defineOptions({
    layout: {
        title: 'Adicionar dependente',
    },
});

const form = useForm({
    name: '',
    birth_date: '',
    document: '',
    phone: '',
    relationship: '',
    responsible_phone: '',
});

function submit() {
    form.post(patientPortal.dependents.store().url);
}
</script>

<template>
    <Head title="Adicionar dependente" />

    <form class="grid gap-6" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="name">Nome do dependente</Label>
                <Input id="name" v-model="form.name" required autofocus />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="birth_date">Data de nascimento</Label>
                <Input
                    id="birth_date"
                    v-model="form.birth_date"
                    type="date"
                    required
                />
                <InputError :message="form.errors.birth_date" />
            </div>
            <div class="grid gap-2">
                <Label for="document">CPF (opcional)</Label>
                <Input
                    id="document"
                    :model-value="form.document"
                    @update:model-value="
                        (v) => (form.document = maskCpf(String(v)))
                    "
                />
                <InputError :message="form.errors.document" />
            </div>
            <div class="grid gap-2">
                <Label for="phone">Telefone do dependente (opcional)</Label>
                <PhoneInput id="phone" v-model="form.phone" />
            </div>
            <div class="grid gap-2">
                <Label for="relationship">Sua relação com o dependente</Label>
                <Input
                    id="relationship"
                    v-model="form.relationship"
                    placeholder="Mãe, pai, tutor(a)..."
                    required
                />
                <InputError :message="form.errors.relationship" />
            </div>
            <div class="grid gap-2">
                <Label for="responsible_phone">Seu telefone</Label>
                <PhoneInput
                    id="responsible_phone"
                    v-model="form.responsible_phone"
                />
                <InputError :message="form.errors.responsible_phone" />
            </div>
        </div>

        <Button
            type="submit"
            class="w-fit"
            :disabled="form.processing"
            data-test="add-dependent-button"
        >
            <Spinner v-if="form.processing" />
            Adicionar dependente
        </Button>
    </form>
</template>

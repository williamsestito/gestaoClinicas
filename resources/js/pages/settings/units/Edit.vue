<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/settings/units';
import { WEEKDAYS } from '@/types/organization';
import type { Unit } from '@/types/organization';

const props = defineProps<{
    unit: Unit & {
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
}>();

const form = useForm({
    name: props.unit.name,
    phone: props.unit.phone ?? '',
    whatsapp: props.unit.whatsapp ?? '',
    email: props.unit.email ?? '',
    timezone: props.unit.timezone,
});

function submit() {
    form.put(update(props.unit.id).url);
}

function dayLabel(day: number): string {
    return WEEKDAYS.find((w) => w.value === day)?.label ?? String(day);
}
</script>

<template>
    <Head :title="`Editar ${unit.name}`" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Editar unidade"
            :description="unit.name"
        />

        <form class="grid max-w-xl gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="unit-name">Nome</Label>
                <Input id="unit-name" v-model="form.name" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="unit-phone">Telefone</Label>
                <Input id="unit-phone" v-model="form.phone" />
                <InputError :message="form.errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="unit-whatsapp">WhatsApp</Label>
                <Input id="unit-whatsapp" v-model="form.whatsapp" />
                <InputError :message="form.errors.whatsapp" />
            </div>

            <div class="grid gap-2">
                <Label for="unit-email">E-mail</Label>
                <Input id="unit-email" v-model="form.email" type="email" />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="unit-timezone">Fuso horário</Label>
                <Input id="unit-timezone" v-model="form.timezone" />
                <InputError :message="form.errors.timezone" />
            </div>

            <Button type="submit" class="w-fit" :disabled="form.processing"
                >Salvar</Button
            >
        </form>

        <Card v-if="unit.address">
            <CardHeader>
                <CardTitle class="text-base">Endereço</CardTitle>
            </CardHeader>
            <CardContent class="text-sm text-muted-foreground">
                {{ unit.address.street }}, {{ unit.address.number }} —
                {{ unit.address.city }}/{{ unit.address.state }}
            </CardContent>
        </Card>

        <Card v-if="unit.opening_hours.length">
            <CardHeader>
                <CardTitle class="text-base"
                    >Horários de funcionamento</CardTitle
                >
            </CardHeader>
            <CardContent class="grid gap-1 text-sm text-muted-foreground">
                <div v-for="(hour, index) in unit.opening_hours" :key="index">
                    {{ dayLabel(hour.day_of_week) }}: {{ hour.opens_at }} às
                    {{ hour.closes_at }}
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarClock,
    ChevronRight,
    MessageCircle,
    Phone,
    UserPlus,
} from '@lucide/vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { initials } from '@/lib/initials';
import { formatDateBr, formatDateTimeBr } from '@/lib/masks';
import { buildWhatsAppUrl } from '@/lib/whatsapp';
import patientPortal from '@/routes/patient-portal';

function buildPhoneUrl(rawPhone: string): string {
    return `tel:${rawPhone.replace(/\D/g, '')}`;
}

type AppointmentSummary = { starts_at: string; status_label: string };

defineProps<{
    patients: Array<{
        id: string;
        name: string;
        birth_date: string;
        role: 'self' | 'dependent';
        role_label: string;
        photo_url: string | null;
        next_appointment: AppointmentSummary | null;
        last_appointment: AppointmentSummary | null;
        pending_requests_count: number;
    }>;
    clinicContact: {
        name: string;
        phone: string | null;
        whatsapp: string | null;
    } | null;
}>();

defineOptions({
    layout: {
        title: 'Início',
    },
});
</script>

<template>
    <Head title="Portal do paciente" />

    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-xl font-semibold">Bem-vindo(a) de volta</h1>
            <p class="text-sm text-muted-foreground">
                Acompanhe seus agendamentos e os de quem você cuida.
            </p>
        </div>

        <div class="grid gap-4">
            <Card v-for="patient in patients" :key="patient.id">
                <CardContent class="flex flex-col gap-4 py-4">
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <Link
                            :href="patientPortal.patients.edit(patient.id)"
                            class="group flex flex-1 items-center gap-3"
                        >
                            <Avatar class="size-12 border border-border">
                                <AvatarImage
                                    v-if="patient.photo_url"
                                    :src="patient.photo_url"
                                    :alt="patient.name"
                                />
                                <AvatarFallback>
                                    {{ initials(patient.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <div>
                                <p
                                    class="flex items-center gap-1 font-medium group-hover:underline"
                                >
                                    {{ patient.name }}
                                    <ChevronRight
                                        class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                                    />
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Nascimento:
                                    {{ formatDateBr(patient.birth_date) }}
                                </p>
                            </div>
                        </Link>
                        <Badge variant="secondary" class="w-fit">
                            {{ patient.role_label }}
                        </Badge>
                    </div>

                    <div
                        class="grid gap-3 border-t border-border pt-4 sm:grid-cols-2"
                    >
                        <div>
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Próxima consulta
                            </p>
                            <p v-if="patient.next_appointment" class="text-sm">
                                {{
                                    formatDateTimeBr(
                                        patient.next_appointment.starts_at,
                                    )
                                }}
                                ·
                                {{ patient.next_appointment.status_label }}
                            </p>
                            <p v-else class="text-sm text-muted-foreground">
                                Nenhuma agendada
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Última consulta
                            </p>
                            <p v-if="patient.last_appointment" class="text-sm">
                                {{
                                    formatDateTimeBr(
                                        patient.last_appointment.starts_at,
                                        { withTime: false },
                                    )
                                }}
                            </p>
                            <p v-else class="text-sm text-muted-foreground">
                                Ainda sem histórico
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-2 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <Link
                            v-if="patient.pending_requests_count > 0"
                            :href="patientPortal.appointments.index(patient.id)"
                            class="text-sm text-primary underline underline-offset-2"
                        >
                            {{ patient.pending_requests_count }}
                            pré-agendamento(s) aguardando confirmação
                        </Link>
                        <span v-else />
                        <div class="flex gap-2">
                            <Button as-child variant="outline" size="sm">
                                <Link
                                    :href="
                                        patientPortal.appointments.index(
                                            patient.id,
                                        )
                                    "
                                >
                                    <CalendarClock class="mr-2 size-4" />
                                    Ver agendamentos
                                </Link>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card v-if="clinicContact">
            <CardContent
                class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p class="font-medium">Fale conosco</p>
                    <p class="text-sm text-muted-foreground">
                        {{ clinicContact.name }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="buildWhatsAppUrl(clinicContact.whatsapp)"
                        as-child
                        variant="outline"
                        size="sm"
                    >
                        <a
                            :href="buildWhatsAppUrl(clinicContact.whatsapp)!"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <MessageCircle class="mr-2 size-4" />
                            WhatsApp
                        </a>
                    </Button>
                    <Button
                        v-if="clinicContact.phone"
                        as-child
                        variant="outline"
                        size="sm"
                    >
                        <a :href="buildPhoneUrl(clinicContact.phone)">
                            <Phone class="mr-2 size-4" />
                            Ligar
                        </a>
                    </Button>
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-end">
            <Button as-child size="sm" variant="ghost">
                <Link :href="patientPortal.dependents.create()">
                    <UserPlus class="mr-2 size-4" />
                    Adicionar dependente
                </Link>
            </Button>
        </div>
    </div>
</template>

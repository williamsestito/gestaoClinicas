<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import ImageUploadField from '@/components/ImageUploadField.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import PatientCoreForm from '@/components/patients/PatientCoreForm.vue';
import type { EditablePatient } from '@/components/patients/PatientCoreForm.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { maskCpf } from '@/lib/masks';
import { dashboard } from '@/routes';
import { index } from '@/routes/settings/patients';
import {
    store as storeEmergencyContact,
    destroy as destroyEmergencyContact,
} from '@/routes/settings/patients/emergency-contacts';
import { update as updatePhoto } from '@/routes/settings/patients/photo';
import {
    store as storeResponsible,
    destroy as destroyResponsible,
} from '@/routes/settings/patients/responsibles';
import {
    store as storeSessionPackage,
    close as closeSessionPackage,
} from '@/routes/settings/patients/session-packages';
import type { AddressForm } from '@/types/organization';

type ResponsibleRow = {
    id: string;
    name: string;
    phone: string;
    relationship: string;
    is_legal_guardian: boolean;
    is_financial_responsible: boolean;
    is_authorized_representative: boolean;
};

type EmergencyContactRow = {
    id: string;
    name: string;
    relationship: string;
    phone_primary: string;
    phone_secondary: string | null;
};

type SessionPackageRow = {
    id: string;
    service_name: string | null;
    total_sessions: number;
    remaining_sessions: number;
    expires_at: string | null;
    status: 'active' | 'inactive';
    is_expired: boolean;
};

const props = defineProps<{
    patient: EditablePatient & { photo_url: string | null };
    address: AddressForm | null;
    states: string[];
    responsibles: ResponsibleRow[];
    emergencyContacts: EmergencyContactRow[];
    sessionPackages: SessionPackageRow[];
    services: { id: string; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Início', href: dashboard() },
            { title: 'Pacientes', href: index() },
            { title: 'Editar paciente' },
        ],
    },
});

function cancel() {
    router.get(index().url);
}

const photoForm = useForm({ photo: null as File | null });

function submitPhoto() {
    if (!photoForm.photo) {
        return;
    }

    photoForm.post(updatePhoto(props.patient.id).url, {
        forceFormData: true,
        preserveScroll: true,
    });
}

const responsibleForm = useForm({
    name: '',
    document: '',
    phone: '',
    relationship: '',
    is_legal_guardian: true,
    is_financial_responsible: false,
    is_authorized_representative: false,
});

function submitResponsible() {
    responsibleForm.post(storeResponsible(props.patient.id).url, {
        preserveScroll: true,
        onSuccess: () => responsibleForm.reset(),
    });
}

function removeResponsible(responsibleId: string) {
    router.delete(destroyResponsible([props.patient.id, responsibleId]).url, {
        preserveScroll: true,
    });
}

const emergencyContactForm = useForm({
    name: '',
    relationship: '',
    phone_primary: '',
    phone_secondary: '',
});

function submitEmergencyContact() {
    emergencyContactForm.post(storeEmergencyContact(props.patient.id).url, {
        preserveScroll: true,
        onSuccess: () => emergencyContactForm.reset(),
    });
}

function removeEmergencyContact(contactId: string) {
    router.delete(destroyEmergencyContact([props.patient.id, contactId]).url, {
        preserveScroll: true,
    });
}

const sessionPackageForm = useForm({
    service_id: '',
    total_sessions: 10,
    expires_at: '',
});

function submitSessionPackage() {
    sessionPackageForm.post(storeSessionPackage(props.patient.id).url, {
        preserveScroll: true,
        onSuccess: () => sessionPackageForm.reset(),
    });
}

function closePackage(packageId: string) {
    router.patch(
        closeSessionPackage([props.patient.id, packageId]).url,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Editar paciente" />

    <div class="flex flex-col space-y-8 p-4">
        <PageHeader title="Editar paciente" :description="patient.name">
            <template #actions>
                <Badge v-if="patient.has_portal_account" variant="secondary">
                    Tem conta no portal
                </Badge>
            </template>
        </PageHeader>

        <PatientCoreForm
            mode="edit"
            :patient="patient"
            :address="address"
            :states="states"
            @cancel="cancel"
        />

        <Separator />

        <section class="grid gap-4">
            <h3 class="text-sm font-medium">Foto (opcional)</h3>
            <ImageUploadField
                id="patient-photo"
                v-model="photoForm.photo"
                label="Foto do paciente"
                :current-url="patient.photo_url"
            />
            <InputError :message="photoForm.errors.photo" />
            <Button
                type="button"
                class="w-fit"
                :disabled="!photoForm.photo || photoForm.processing"
                @click="submitPhoto"
            >
                Salvar foto
            </Button>
        </section>

        <Separator />

        <section class="grid gap-4">
            <div>
                <h3 class="text-sm font-medium">Responsáveis</h3>
                <p class="text-sm text-muted-foreground">
                    Responsável legal, financeiro e/ou representante autorizado.
                </p>
            </div>

            <div
                v-for="responsible in responsibles"
                :key="responsible.id"
                class="flex items-center justify-between rounded-lg border p-4"
            >
                <div>
                    <p class="font-medium">{{ responsible.name }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ responsible.relationship }} — {{ responsible.phone }}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="removeResponsible(responsible.id)"
                >
                    Remover
                </Button>
            </div>

            <form
                class="grid gap-3 rounded-lg border p-4"
                @submit.prevent="submitResponsible"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="new-responsible-name">Nome</Label>
                        <Input
                            id="new-responsible-name"
                            v-model="responsibleForm.name"
                        />
                        <InputError :message="responsibleForm.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="new-responsible-relationship">
                            Relação com o paciente
                        </Label>
                        <Input
                            id="new-responsible-relationship"
                            v-model="responsibleForm.relationship"
                        />
                        <InputError
                            :message="responsibleForm.errors.relationship"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="new-responsible-phone">Telefone</Label>
                        <PhoneInput
                            id="new-responsible-phone"
                            v-model="responsibleForm.phone"
                        />
                        <InputError :message="responsibleForm.errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="new-responsible-document">
                            CPF (opcional)
                        </Label>
                        <Input
                            id="new-responsible-document"
                            :model-value="responsibleForm.document"
                            @update:model-value="
                                (value) =>
                                    (responsibleForm.document = maskCpf(
                                        String(value),
                                    ))
                            "
                        />
                    </div>
                </div>
                <div class="flex flex-wrap gap-4">
                    <Label class="flex items-center gap-2 font-normal">
                        <Checkbox
                            v-model:model-value="
                                responsibleForm.is_legal_guardian
                            "
                        />
                        Responsável legal
                    </Label>
                    <Label class="flex items-center gap-2 font-normal">
                        <Checkbox
                            v-model:model-value="
                                responsibleForm.is_financial_responsible
                            "
                        />
                        Responsável financeiro
                    </Label>
                    <Label class="flex items-center gap-2 font-normal">
                        <Checkbox
                            v-model:model-value="
                                responsibleForm.is_authorized_representative
                            "
                        />
                        Representante autorizado
                    </Label>
                </div>
                <InputError
                    :message="responsibleForm.errors.is_legal_guardian"
                />
                <Button
                    type="submit"
                    class="w-fit"
                    :disabled="responsibleForm.processing"
                >
                    Adicionar responsável
                </Button>
            </form>
        </section>

        <Separator />

        <section class="grid gap-4">
            <div>
                <h3 class="text-sm font-medium">Contatos de emergência</h3>
                <p class="text-sm text-muted-foreground">
                    Ao menos um é obrigatório.
                </p>
            </div>

            <div
                v-for="contact in emergencyContacts"
                :key="contact.id"
                class="flex items-center justify-between rounded-lg border p-4"
            >
                <div>
                    <p class="font-medium">{{ contact.name }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ contact.relationship }} — {{ contact.phone_primary }}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="removeEmergencyContact(contact.id)"
                >
                    Remover
                </Button>
            </div>

            <form
                class="grid gap-3 rounded-lg border p-4"
                @submit.prevent="submitEmergencyContact"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="new-contact-name">Nome</Label>
                        <Input
                            id="new-contact-name"
                            v-model="emergencyContactForm.name"
                        />
                        <InputError
                            :message="emergencyContactForm.errors.name"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="new-contact-relationship">
                            Relação com o paciente
                        </Label>
                        <Input
                            id="new-contact-relationship"
                            v-model="emergencyContactForm.relationship"
                        />
                        <InputError
                            :message="emergencyContactForm.errors.relationship"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="new-contact-phone">
                            Telefone principal
                        </Label>
                        <PhoneInput
                            id="new-contact-phone"
                            v-model="emergencyContactForm.phone_primary"
                        />
                        <InputError
                            :message="emergencyContactForm.errors.phone_primary"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="new-contact-phone-alt">
                            Telefone alternativo (opcional)
                        </Label>
                        <PhoneInput
                            id="new-contact-phone-alt"
                            v-model="emergencyContactForm.phone_secondary"
                        />
                    </div>
                </div>
                <Button
                    type="submit"
                    class="w-fit"
                    :disabled="emergencyContactForm.processing"
                >
                    Adicionar contato
                </Button>
            </form>
        </section>

        <Separator />

        <section class="grid gap-4">
            <div>
                <h3 class="text-sm font-medium">Pacotes de sessões</h3>
                <p class="text-sm text-muted-foreground">
                    Sem cobrança/pagamento vinculado nesta etapa — apenas
                    contagem de sessões.
                </p>
            </div>

            <div
                v-for="pkg in sessionPackages"
                :key="pkg.id"
                class="flex items-center justify-between rounded-lg border p-4"
            >
                <div>
                    <p class="font-medium">
                        {{ pkg.remaining_sessions }} de {{ pkg.total_sessions }}
                        sessão(ões) restantes
                        <span v-if="pkg.service_name">
                            — {{ pkg.service_name }}</span
                        >
                    </p>
                    <p class="text-sm text-muted-foreground">
                        <span v-if="pkg.expires_at">
                            Expira em {{ pkg.expires_at }}
                        </span>
                        <span v-if="pkg.is_expired" class="text-destructive">
                            (expirado)</span
                        >
                        <span v-if="pkg.status === 'inactive'">
                            — Encerrado</span
                        >
                    </p>
                </div>
                <Button
                    v-if="pkg.status === 'active'"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="closePackage(pkg.id)"
                >
                    Encerrar
                </Button>
            </div>

            <form
                class="grid gap-3 rounded-lg border p-4"
                @submit.prevent="submitSessionPackage"
            >
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="new-package-service">
                            Serviço (opcional)
                        </Label>
                        <select
                            id="new-package-service"
                            v-model="sessionPackageForm.service_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Qualquer serviço</option>
                            <option
                                v-for="service in services"
                                :key="service.id"
                                :value="service.id"
                            >
                                {{ service.name }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="new-package-total">
                            Total de sessões
                        </Label>
                        <Input
                            id="new-package-total"
                            v-model.number="sessionPackageForm.total_sessions"
                            type="number"
                            min="1"
                            max="999"
                        />
                        <InputError
                            :message="sessionPackageForm.errors.total_sessions"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="new-package-expires">
                            Expira em (opcional)
                        </Label>
                        <Input
                            id="new-package-expires"
                            v-model="sessionPackageForm.expires_at"
                            type="date"
                        />
                        <InputError
                            :message="sessionPackageForm.errors.expires_at"
                        />
                    </div>
                </div>
                <Button
                    type="submit"
                    class="w-fit"
                    :disabled="sessionPackageForm.processing"
                >
                    Criar pacote
                </Button>
            </form>
        </section>
    </div>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { maskCpf } from '@/lib/masks';
import { store, update } from '@/routes/settings/professionals';

export type EligibleUser = { id: number; name: string; email: string };

export type EditableProfessional = {
    id: string;
    name: string;
    social_name: string | null;
    display_name: string;
    email: string | null;
    phone: string | null;
    document: string | null;
    birth_date: string | null;
    bio: string | null;
    is_public: boolean;
};

const props = withDefaults(
    defineProps<{
        mode: 'create' | 'edit';
        professional?: EditableProfessional;
        eligibleUsers?: EligibleUser[];
    }>(),
    {
        professional: undefined,
        eligibleUsers: () => [],
    },
);

const emit = defineEmits<{
    success: [];
    cancel: [];
}>();

const form = useForm({
    name: props.professional?.name ?? '',
    social_name: props.professional?.social_name ?? '',
    display_name: props.professional?.display_name ?? '',
    email: props.professional?.email ?? '',
    phone: props.professional?.phone ?? '',
    document: '',
    birth_date: props.professional?.birth_date ?? '',
    bio: props.professional?.bio ?? '',
    is_public: props.professional?.is_public ?? false,
    user_id: undefined as number | undefined,
});

function onDocumentInput(event: Event) {
    form.document = maskCpf((event.target as HTMLInputElement).value);
}

function submit() {
    if (props.mode === 'create') {
        form.post(store().url, { onSuccess: () => emit('success') });

        return;
    }

    if (props.professional) {
        form.put(update(props.professional.id).url, {
            onSuccess: () => emit('success'),
        });
    }
}
</script>

<template>
    <form class="flex flex-col gap-4" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="professional-name">Nome</Label>
                <Input id="professional-name" v-model="form.name" autofocus />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="professional-social-name"
                    >Nome social (opcional)</Label
                >
                <Input
                    id="professional-social-name"
                    v-model="form.social_name"
                />
                <InputError :message="form.errors.social_name" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="professional-display-name"
                    >Nome de exibição (opcional — usa o nome social ou o nome,
                    se vazio)</Label
                >
                <Input
                    id="professional-display-name"
                    v-model="form.display_name"
                />
                <InputError :message="form.errors.display_name" />
            </div>

            <div class="grid gap-2">
                <Label for="professional-email"
                    >E-mail profissional (opcional)</Label
                >
                <Input
                    id="professional-email"
                    v-model="form.email"
                    type="email"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="professional-phone">Telefone (opcional)</Label>
                <PhoneInput id="professional-phone" v-model="form.phone" />
                <InputError :message="form.errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="professional-document">CPF (opcional)</Label>
                <Input
                    id="professional-document"
                    :model-value="form.document"
                    :placeholder="professional?.document ?? undefined"
                    @input="onDocumentInput"
                />
                <p class="text-xs text-muted-foreground">
                    Deixe em branco para manter o documento atual.
                </p>
                <InputError :message="form.errors.document" />
            </div>

            <div class="grid gap-2">
                <Label for="professional-birth-date"
                    >Data de nascimento (opcional)</Label
                >
                <Input
                    id="professional-birth-date"
                    v-model="form.birth_date"
                    type="date"
                />
                <InputError :message="form.errors.birth_date" />
            </div>

            <div v-if="mode === 'create'" class="grid gap-2 sm:col-span-2">
                <Label for="professional-user"
                    >Usuário vinculado (opcional)</Label
                >
                <select
                    id="professional-user"
                    v-model="form.user_id"
                    class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option :value="undefined">Nenhum</option>
                    <option
                        v-for="eligibleUser in eligibleUsers"
                        :key="eligibleUser.id"
                        :value="eligibleUser.id"
                    >
                        {{ eligibleUser.name }} ({{ eligibleUser.email }})
                    </option>
                </select>
                <p class="text-xs text-muted-foreground">
                    Vincular um usuário não concede nenhum acesso ou permissão —
                    isso continua dependendo do papel atribuído a ele na
                    clínica.
                </p>
                <InputError :message="form.errors.user_id" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="professional-bio">Biografia (opcional)</Label>
                <Textarea id="professional-bio" v-model="form.bio" rows="4" />
                <p class="text-xs text-muted-foreground">
                    Exibida publicamente somente se "Exibir no site" estiver
                    marcado.
                </p>
                <InputError :message="form.errors.bio" />
            </div>

            <div v-if="mode === 'edit'" class="grid gap-2 sm:col-span-2">
                <Label class="flex items-center gap-2 font-normal">
                    <Checkbox v-model:model-value="form.is_public" />
                    Exibir no site público
                </Label>
                <p class="text-xs text-muted-foreground">
                    Quando marcado, o nome de exibição, a foto e a biografia
                    podem aparecer na página pública da clínica. Nenhum dado
                    sensível é exibido.
                </p>
                <InputError :message="form.errors.is_public" />
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
                        ? 'Criar profissional'
                        : 'Salvar alterações'
                }}
            </Button>
        </div>
    </form>
</template>

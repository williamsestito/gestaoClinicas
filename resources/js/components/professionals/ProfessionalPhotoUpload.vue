<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ImageUploadField from '@/components/ImageUploadField.vue';
import { Button } from '@/components/ui/button';
import {
    destroy as destroyPhotoRoute,
    update as updatePhotoRoute,
} from '@/routes/settings/professionals/photo';

const props = defineProps<{
    professionalId: string;
    photoUrl: string | null;
}>();

const photoFile = ref<File | null>(null);
const form = useForm({ photo: null as File | null });
const removing = ref(false);

function upload() {
    if (!photoFile.value) {
        return;
    }

    form.photo = photoFile.value;
    form.post(updatePhotoRoute(props.professionalId).url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            photoFile.value = null;
        },
    });
}

function removePhoto() {
    removing.value = true;
    form.delete(destroyPhotoRoute(props.professionalId).url, {
        preserveScroll: true,
        onFinish: () => (removing.value = false),
    });
}
</script>

<template>
    <div class="grid gap-3">
        <ImageUploadField
            id="professional-photo"
            v-model="photoFile"
            label="Foto"
            :current-url="photoUrl"
            helper-text="JPG, PNG ou WEBP — até 2MB."
        />
        <div class="flex items-center gap-2">
            <Button
                type="button"
                size="sm"
                :disabled="!photoFile || form.processing"
                @click="upload"
            >
                Enviar foto
            </Button>
            <Button
                v-if="photoUrl"
                type="button"
                variant="secondary"
                size="sm"
                :disabled="removing"
                @click="removePhoto"
            >
                Remover foto
            </Button>
        </div>
    </div>
</template>

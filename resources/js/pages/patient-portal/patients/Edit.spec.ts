import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import Edit from './Edit.vue';

const { profileFormState, photoFormState, profilePost, photoPost } = vi.hoisted(
    () => ({
        profileFormState: {
            name: '',
            preferred_name: '',
            document: '',
            phone: '',
            whatsapp: '',
            email: '',
            address: null as unknown,
            errors: {} as Record<string, string>,
            processing: false,
            put: vi.fn(),
        },
        photoFormState: {
            photo: null as File | null,
            errors: {} as Record<string, string>,
            processing: false,
            post: vi.fn(),
            reset: vi.fn(),
        },
        profilePost: vi.fn(),
        photoPost: vi.fn(),
    }),
);

profileFormState.put = profilePost;
photoFormState.post = photoPost;

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    // reactive() aqui dentro (não em vi.hoisted) — o import de "vue" só
    // termina de resolver depois que os blocos hoisted já rodaram; chamar
    // reactive() lá de dentro dispara "Cannot access '...' before
    // initialization". A seleção de arquivo é sempre simulada via evento
    // real de input (ver ImageUploadField.spec.ts) para passar pelo
    // defineModel do componente — mutar a propriedade crua por fora do
    // Proxy reativo não notificaria o Vue.
    useForm: (initial: Record<string, unknown> = {}) => {
        if ('photo' in initial) {
            return reactive(photoFormState);
        }

        Object.assign(profileFormState, initial);

        return reactive(profileFormState);
    },
}));

const patient = {
    id: 'patient-1',
    name: 'Ana Souza',
    preferred_name: null,
    document: null,
    birth_date: '1990-05-10',
    phone: null,
    whatsapp: null,
    email: null,
    photo_url: null,
};

function selectPhoto(wrapper: ReturnType<typeof mount>) {
    const file = new File(['fake-image-content'], 'foto.png', {
        type: 'image/png',
    });
    const input = wrapper.find('input[type="file"]')
        .element as HTMLInputElement;
    Object.defineProperty(input, 'files', {
        value: [file],
        configurable: true,
    });

    return wrapper.find('input[type="file"]').trigger('change');
}

describe('patient-portal/patients/Edit', () => {
    beforeEach(() => {
        profilePost.mockClear();
        photoPost.mockClear();
        photoFormState.photo = null;
    });

    it('renders the patient name and birth date in a friendly header, with initials as the avatar fallback', () => {
        const wrapper = mount(Edit, {
            props: { patient, address: null, states: [] },
        });

        expect(wrapper.text()).toContain('Ana Souza');
        expect(wrapper.text()).toContain('10/05/1990');
        expect(wrapper.text()).toContain('AS');
    });

    it('disables the photo save button until a file is selected', async () => {
        const wrapper = mount(Edit, {
            props: { patient, address: null, states: [] },
        });

        const saveButton = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Salvar foto'))!;
        expect(saveButton.attributes('disabled')).toBeDefined();

        await selectPhoto(wrapper);

        expect(saveButton.attributes('disabled')).toBeUndefined();
    });

    it('submits the photo as multipart form data to the photo endpoint', async () => {
        const wrapper = mount(Edit, {
            props: { patient, address: null, states: [] },
        });

        await selectPhoto(wrapper);

        const saveButton = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Salvar foto'))!;
        await saveButton.trigger('click');

        expect(photoPost).toHaveBeenCalledWith(
            `/portal/pacientes/${patient.id}/foto`,
            expect.objectContaining({ forceFormData: true }),
        );
    });

    it('submits the profile form to the patient update endpoint', async () => {
        const wrapper = mount(Edit, {
            props: { patient, address: null, states: [] },
        });

        await wrapper.find('form').trigger('submit');

        expect(profilePost).toHaveBeenCalledWith(
            `/portal/pacientes/${patient.id}`,
        );
    });
});

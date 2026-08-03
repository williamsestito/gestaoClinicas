import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import ProfessionalForm from './ProfessionalForm.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (initial: Record<string, unknown>) =>
        reactive({
            ...initial,
            errors: {},
            processing: false,
            post: vi.fn(),
            put: vi.fn(),
        }),
}));

describe('ProfessionalForm', () => {
    it('applies the CPF mask as the user types the document', async () => {
        const wrapper = mount(ProfessionalForm, {
            props: { mode: 'create' },
        });

        const documentInput = wrapper.find('#professional-document');
        await documentInput.setValue('52998224725');

        expect((documentInput.element as HTMLInputElement).value).toBe(
            '529.982.247-25',
        );
    });

    it('applies the phone mask as the user types', async () => {
        const wrapper = mount(ProfessionalForm, {
            props: { mode: 'create' },
        });

        const phoneInput = wrapper.find('#professional-phone');
        await phoneInput.setValue('11987654321');

        expect((phoneInput.element as HTMLInputElement).value).toBe(
            '(11) 98765-4321',
        );
    });

    it('only shows the user selection field in create mode', () => {
        const createWrapper = mount(ProfessionalForm, {
            props: { mode: 'create', eligibleUsers: [] },
        });
        expect(createWrapper.find('#professional-user').exists()).toBe(true);

        const editWrapper = mount(ProfessionalForm, {
            props: {
                mode: 'edit',
                professional: {
                    id: '1',
                    name: 'Dra. Ana',
                    social_name: null,
                    display_name: 'Dra. Ana',
                    email: null,
                    phone: null,
                    document: '***.***.***-12',
                    birth_date: null,
                    bio: null,
                    is_public: false,
                },
            },
        });
        expect(editWrapper.find('#professional-user').exists()).toBe(false);
    });

    it('never pre-fills the document field with the masked value received from the backend', () => {
        const wrapper = mount(ProfessionalForm, {
            props: {
                mode: 'edit',
                professional: {
                    id: '1',
                    name: 'Dra. Ana',
                    social_name: null,
                    display_name: 'Dra. Ana',
                    email: null,
                    phone: null,
                    document: '***.***.***-12',
                    birth_date: null,
                    bio: null,
                    is_public: false,
                },
            },
        });

        const documentInput = wrapper.find('#professional-document')
            .element as HTMLInputElement;
        expect(documentInput.value).toBe('');
        expect(documentInput.placeholder).toContain('***.***.***-12');
    });
});

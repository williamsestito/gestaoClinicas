import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import Register from './Register.vue';

const { formState, postMock } = vi.hoisted(() => ({
    formState: {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        registering_for: 'self' as 'self' | 'dependent',
        birth_date: '',
        document: '',
        phone: '',
        photo: null as File | null,
        dependent_name: '',
        dependent_birth_date: '',
        dependent_document: '',
        dependent_phone: '',
        relationship: '',
        responsible_phone: '',
        website: '',
        form_rendered_at: 0,
        errors: {} as Record<string, string>,
        processing: false,
        post: vi.fn(),
    },
    postMock: vi.fn(),
}));

formState.post = postMock;

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { props: ['href'], template: '<a><slot /></a>' },
    // reactive(): o próprio componente lê form.registering_for no
    // template (v-if) — sem envolver num Proxy reativo, mutá-lo ao clicar
    // no botão "Um dependente" nunca dispararia um novo render.
    useForm: (initial: Record<string, unknown> = {}) => {
        Object.assign(formState, initial);

        return reactive(formState);
    },
}));

const emptyPrefill = { name: null, phone: null, email: null, document: null };

describe('patient-portal/Register', () => {
    beforeEach(() => {
        postMock.mockReset();
        formState.name = '';
        formState.phone = '';
        formState.email = '';
        formState.document = '';
        formState.photo = null;
        formState.registering_for = 'self';
    });

    it('shows the "cadastro indisponível" message when there is no organization', () => {
        const wrapper = mount(Register, {
            props: { organizationConfigured: false, prefill: emptyPrefill },
        });

        expect(wrapper.text()).toContain('Cadastro indisponível no momento');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('prefills name, phone, email and document from the landing page data', () => {
        mount(Register, {
            props: {
                organizationConfigured: true,
                prefill: {
                    name: 'Ana Souza',
                    phone: '(47) 99999-0000',
                    email: 'ana@example.com',
                    document: '52998224725',
                },
            },
        });

        expect(formState.name).toBe('Ana Souza');
        expect(formState.phone).toBe('(47) 99999-0000');
        expect(formState.email).toBe('ana@example.com');
        expect(formState.document).toBe('529.982.247-25');
    });

    it('leaves the fields empty when there is nothing to prefill', () => {
        mount(Register, {
            props: { organizationConfigured: true, prefill: emptyPrefill },
        });

        expect(formState.name).toBe('');
        expect(formState.phone).toBe('');
        expect(formState.email).toBe('');
        expect(formState.document).toBe('');
    });

    it('shows a photo picker with an edit icon only for the "self" path', async () => {
        const wrapper = mount(Register, {
            props: { organizationConfigured: true, prefill: emptyPrefill },
        });

        expect(wrapper.find('input#photo').exists()).toBe(true);

        const dependentButton = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Um dependente'))!;
        await dependentButton.trigger('click');

        expect(wrapper.find('input#photo').exists()).toBe(false);
    });

    it('submits the form as multipart data (photo included)', async () => {
        const wrapper = mount(Register, {
            props: { organizationConfigured: true, prefill: emptyPrefill },
        });

        await wrapper.find('form').trigger('submit');

        expect(postMock).toHaveBeenCalledWith(
            expect.any(String),
            expect.objectContaining({ forceFormData: true }),
        );
    });
});

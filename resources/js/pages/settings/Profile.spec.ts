import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import Profile from './Profile.vue';

const { routerMock, formInstances } = vi.hoisted(() => ({
    routerMock: {
        patch: vi.fn(),
        put: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    },
    formInstances: [] as Record<string, unknown>[],
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>' },
    router: routerMock,
    usePage: () => ({
        props: {
            auth: {
                user: {
                    name: 'Ana Souza',
                    email: 'ana@example.test',
                    email_verified_at: '2026-01-01T00:00:00.000Z',
                },
            },
        },
    }),
    useForm: (initial: Record<string, unknown>) => {
        const instance = reactive({
            ...initial,
            errors: {},
            processing: false,
            isDirty: false,
            transform: (
                callback: (data: Record<string, unknown>) => unknown,
            ) => ({
                patch: vi.fn(),
                post: vi.fn(),
                data: callback(initial),
            }),
            patch: vi.fn(),
            post: vi.fn(),
        });
        formInstances.push(instance);

        return instance;
    },
}));

function makeProps() {
    return {
        mustVerifyEmail: true,
        status: undefined,
        states: ['SC', 'SP'],
        profile: {
            phone: '(47) 99999-1234',
            cpf: '39053344705',
            photo_url: null,
            address: {
                postal_code: '01310100',
                street: 'Av. Paulista',
                number: '1000',
                complement: '',
                neighborhood: 'Bela Vista',
                city: 'São Paulo',
                state: 'SP',
            },
        },
    };
}

describe('settings/Profile', () => {
    it('renders the existing phone, cpf and address values', () => {
        const wrapper = mount(Profile, { props: makeProps() });

        expect((wrapper.find('#phone').element as HTMLInputElement).value).toBe(
            '(47) 99999-1234',
        );
        expect((wrapper.find('#cpf').element as HTMLInputElement).value).toBe(
            '39053344705',
        );
        expect(
            (wrapper.find('#address-street').element as HTMLInputElement).value,
        ).toBe('Av. Paulista');
    });

    it('links to the Security page for password changes, not a password field here', () => {
        const wrapper = mount(Profile, { props: makeProps() });

        expect(wrapper.find('input[type="password"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Ir para Segurança');
    });

    it('shows a link to remove the current photo when one exists', () => {
        const wrapper = mount(Profile, {
            props: {
                ...makeProps(),
                profile: {
                    ...makeProps().profile,
                    photo_url: 'https://example.test/photo.jpg',
                },
            },
        });

        expect(wrapper.text()).toContain('Remover foto atual');
    });
});

import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import Index from './Index.vue';

const { routerMock, flashHandlers } = vi.hoisted(() => {
    const flashHandlers: ((event: Event) => void)[] = [];

    return {
        flashHandlers,
        routerMock: {
            patch: vi.fn(),
            put: vi.fn(),
            post: vi.fn(),
            delete: vi.fn(),
            on: vi.fn((event: string, callback: (event: Event) => void) => {
                if (event === 'flash') {
                    flashHandlers.push(callback);
                }

                return () => {
                    const index = flashHandlers.indexOf(callback);

                    if (index !== -1) {
                        flashHandlers.splice(index, 1);
                    }
                };
            }),
        },
    };
});

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    router: routerMock,
    useForm: (initial: Record<string, unknown>) =>
        reactive({
            ...initial,
            errors: {},
            processing: false,
            post: vi.fn(),
            put: vi.fn(),
        }),
}));

vi.mock('vue-sonner', () => ({
    toast: { success: vi.fn(), error: vi.fn() },
}));

function emitFlash(flash: Record<string, unknown>) {
    for (const handler of flashHandlers) {
        handler(new CustomEvent('flash', { detail: { flash } }));
    }
}

function makeMembership(overrides: Record<string, unknown> = {}) {
    return {
        id: 'm-1',
        admin_note: null,
        user: {
            name: 'Ana Souza',
            email: 'ana@example.test',
            phone: '(47) 99999-1111',
            photo_url: null,
            is_active: true,
            last_login_at: null,
        },
        role: { id: 'role-admin', name: 'Administrador da clínica' },
        unit_memberships: [
            {
                unit_id: 'unit-1',
                is_primary: true,
                unit: { id: 'unit-1', name: 'Matriz' },
            },
        ],
        ...overrides,
    };
}

function makeProps() {
    return {
        memberships: [
            makeMembership(),
            makeMembership({
                id: 'm-2',
                user: {
                    name: 'Bruno Lima',
                    email: 'bruno@example.test',
                    phone: null,
                    photo_url: null,
                    is_active: false,
                    last_login_at: null,
                },
                role: { id: 'role-reception', name: 'Recepção' },
                unit_memberships: [],
            }),
        ],
        invitations: [],
        roles: [
            {
                id: 'role-admin',
                name: 'Administrador da clínica',
                is_system: true,
            },
            { id: 'role-reception', name: 'Recepção', is_system: true },
        ],
        units: [{ id: 'unit-1', name: 'Matriz' }],
    };
}

describe('settings/users/Index', () => {
    beforeEach(() => {
        flashHandlers.length = 0;
    });

    it('shows an empty state with a call to action when there are no users', () => {
        const wrapper = mount(Index, {
            props: { ...makeProps(), memberships: [] },
        });

        expect(wrapper.text()).toContain('Nenhum usuário cadastrado ainda.');
        expect(wrapper.find('table').exists()).toBe(false);
    });

    it('renders every user in a real table, with name, email, phone, role, status and primary unit', () => {
        const wrapper = mount(Index, { props: makeProps() });

        const table = wrapper.find('table');
        expect(table.exists()).toBe(true);

        const text = table.text();
        expect(text).toContain('Ana Souza');
        expect(text).toContain('ana@example.test');
        expect(text).toContain('(47) 99999-1111');
        expect(text).toContain('Administrador da clínica');
        expect(text).toContain('Matriz');
        expect(text).toContain('Ativo');
        expect(text).toContain('Bruno Lima');
        expect(text).toContain('Inativo');
    });

    it('filters by search term matching name or email', async () => {
        const wrapper = mount(Index, { props: makeProps() });

        await wrapper
            .find('input[aria-label="Buscar usuários por nome ou e-mail"]')
            .setValue('bruno');

        const table = wrapper.find('table');
        expect(table.text()).toContain('Bruno Lima');
        expect(table.text()).not.toContain('Ana Souza');
    });

    it('filters by status', async () => {
        const wrapper = mount(Index, { props: makeProps() });

        await wrapper
            .find('select[aria-label="Filtrar usuários por status"]')
            .setValue('inactive');

        const table = wrapper.find('table');
        expect(table.text()).toContain('Bruno Lima');
        expect(table.text()).not.toContain('Ana Souza');
    });

    it('filters by role', async () => {
        const wrapper = mount(Index, { props: makeProps() });

        await wrapper
            .find('select[aria-label="Filtrar usuários por papel"]')
            .setValue('role-reception');

        const table = wrapper.find('table');
        expect(table.text()).toContain('Bruno Lima');
        expect(table.text()).not.toContain('Ana Souza');
    });

    it('shows a card layout for mobile alongside the desktop table', () => {
        const wrapper = mount(Index, { props: makeProps() });

        expect(wrapper.find('table').exists()).toBe(true);
        expect(
            wrapper.findAllComponents({ name: 'Card' }).length,
        ).toBeGreaterThan(0);
    });

    it('opens a dialog with the copyable invite link when the "inviteLink" flash arrives', async () => {
        const wrapper = mount(Index, {
            props: makeProps(),
            attachTo: document.body,
        });

        emitFlash({
            inviteLink: {
                email: 'nova@example.test',
                url: 'https://clinica.test/invitations/abc123',
            },
        });
        await wrapper.vm.$nextTick();

        const text = document.body.textContent ?? '';
        expect(text).toContain('Convite criado');
        expect(text).toContain('nova@example.test');

        const linkInput = document.body.querySelector(
            'input[readonly]',
        ) as HTMLInputElement;
        expect(linkInput.value).toBe('https://clinica.test/invitations/abc123');

        wrapper.unmount();
    });

    it('does not show the invite link dialog before any "inviteLink" flash arrives', () => {
        const wrapper = mount(Index, {
            props: makeProps(),
            attachTo: document.body,
        });

        expect(document.body.textContent ?? '').not.toContain('Convite criado');

        wrapper.unmount();
    });

    it('copies the invite link to the clipboard and confirms with a toast', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.assign(navigator, { clipboard: { writeText } });

        const wrapper = mount(Index, {
            props: makeProps(),
            attachTo: document.body,
        });
        emitFlash({
            inviteLink: {
                email: 'nova@example.test',
                url: 'https://clinica.test/invitations/abc123',
            },
        });
        await wrapper.vm.$nextTick();

        const copyButton = document.body.querySelector(
            'button[aria-label="Copiar link do convite"]',
        ) as HTMLButtonElement;
        copyButton.click();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(writeText).toHaveBeenCalledWith(
            'https://clinica.test/invitations/abc123',
        );

        wrapper.unmount();
    });
});

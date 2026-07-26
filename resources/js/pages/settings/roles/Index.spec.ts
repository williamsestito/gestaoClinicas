import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import Index from './Index.vue';

const { routerMock } = vi.hoisted(() => ({
    routerMock: {
        patch: vi.fn(),
        put: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    },
}));

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

function makeRole(overrides: Record<string, unknown> = {}) {
    return {
        id: 'role-1',
        name: 'Administrador da clínica',
        slug: 'administrador-clinica',
        description: 'Gerencia a operação da clínica.',
        is_system: true,
        permissions: Array.from({ length: 38 }, (_, i) => ({ key: `perm-${i}` })),
        organization_memberships_count: 3,
        ...overrides,
    };
}

function makeProps() {
    return {
        roles: [
            makeRole(),
            makeRole({
                id: 'role-2',
                name: 'Financeiro',
                slug: 'financeiro',
                description: 'Acesso à área financeira.',
                is_system: true,
                permissions: [{ key: 'finance.view' }],
                organization_memberships_count: 0,
            }),
        ],
        permissions: {},
    };
}

describe('settings/roles/Index', () => {
    it('shows an empty state with a call to action when there are no roles', () => {
        const wrapper = mount(Index, { props: { ...makeProps(), roles: [] } });

        expect(wrapper.text()).toContain('Nenhum papel cadastrado ainda.');
        expect(wrapper.find('table').exists()).toBe(false);
    });

    it('renders every role in a real table with name, description, permission and user counts, and type', () => {
        const wrapper = mount(Index, { props: makeProps() });

        const table = wrapper.find('table');
        expect(table.exists()).toBe(true);

        const text = table.text();
        expect(text).toContain('Administrador da clínica');
        expect(text).toContain('Gerencia a operação da clínica.');
        expect(text).toContain('38');
        expect(text).toContain('3');
        expect(text).toContain('Sistema');
        expect(text).toContain('Financeiro');
    });

    it('never lists all permission keys directly in the table', () => {
        const wrapper = mount(Index, { props: makeProps() });

        expect(wrapper.find('table').text()).not.toContain('perm-0');
    });

    it('filters by search term matching name or description', async () => {
        const wrapper = mount(Index, { props: makeProps() });

        await wrapper
            .find('input[aria-label="Buscar papéis por nome ou descrição"]')
            .setValue('financeiro');

        const table = wrapper.find('table');
        expect(table.text()).toContain('Financeiro');
        expect(table.text()).not.toContain('Administrador da clínica');
    });

    it('shows a card layout for mobile alongside the desktop table', () => {
        const wrapper = mount(Index, { props: makeProps() });

        expect(wrapper.find('table').exists()).toBe(true);
        expect(wrapper.findAllComponents({ name: 'Card' }).length).toBeGreaterThan(0);
    });
});

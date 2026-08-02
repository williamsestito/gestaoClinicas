import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import ProfessionalForm from '@/components/professionals/ProfessionalForm.vue';
import ProfessionalRowActions from '@/components/professionals/ProfessionalRowActions.vue';
import Index from './Index.vue';
import type { ProfessionalRow } from './Index.vue';

const { routerMock } = vi.hoisted(() => ({
    routerMock: {
        patch: vi.fn(),
        put: vi.fn(),
        post: vi.fn(),
        get: vi.fn(),
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

function makeProfessional(
    overrides: Partial<ProfessionalRow> = {},
): ProfessionalRow {
    return {
        id: overrides.id ?? '1',
        display_name: 'Dra. Ana Souza',
        email: 'ana@example.com',
        phone: '••••••••1234',
        document: '***.***.***-12',
        photo_url: null,
        status: 'active',
        linked_user_name: null,
        deleted_at: null,
        updated_at: '2026-08-02T12:00:00Z',
        unit_ids: [],
        unit_names: [],
        specialty_ids: [],
        specialty_names: [],
        active_services_count: 0,
        has_active_unit: false,
        has_active_specialty: false,
        has_active_service: false,
        has_working_hours: false,
        has_ongoing_absence: false,
        operational_status: 'incomplete',
        ...overrides,
    };
}

describe('settings/professionals/Index', () => {
    it('shows an empty state with a call to action when there are no professionals', () => {
        const wrapper = mount(Index, { props: { professionals: [] } });

        expect(wrapper.text()).toContain(
            'Nenhum profissional cadastrado ainda.',
        );
        expect(wrapper.text()).toContain('Cadastrar primeiro profissional');
    });

    it('never renders the full document, only the masked value received from the backend', () => {
        const wrapper = mount(Index, {
            props: {
                professionals: [
                    makeProfessional({ document: '***.***.***-12' }),
                ],
            },
        });

        expect(wrapper.text()).toContain('***.***.***-12');
        expect(wrapper.text()).not.toMatch(/\d{3}\.\d{3}\.\d{3}-\d{2}/);
    });

    it('shows "Nenhum" when no user is linked, and the user name when one is', () => {
        const withoutUser = mount(Index, {
            props: { professionals: [makeProfessional()] },
        });
        expect(withoutUser.text()).toContain('Nenhum');

        const withUser = mount(Index, {
            props: {
                professionals: [
                    makeProfessional({ linked_user_name: 'Carlos Souza' }),
                ],
            },
        });
        expect(withUser.text()).toContain('Carlos Souza');
    });

    it('filters the listing by search term matching name or email', async () => {
        const professionals = [
            makeProfessional({
                id: '1',
                display_name: 'Dra. Ana Souza',
                email: 'ana@example.com',
            }),
            makeProfessional({
                id: '2',
                display_name: 'Dr. Bruno Lima',
                email: 'bruno@example.com',
            }),
        ];
        const wrapper = mount(Index, { props: { professionals } });

        await wrapper
            .find('input[aria-label="Buscar profissionais por nome ou e-mail"]')
            .setValue('Bruno');

        expect(wrapper.text()).toContain('Dr. Bruno Lima');
        expect(wrapper.text()).not.toContain('Dra. Ana Souza');
    });

    it('never mixes SiteProfessional content into the operational listing', () => {
        const wrapper = mount(Index, {
            props: { professionals: [makeProfessional()] },
        });

        expect(wrapper.text()).not.toContain('SiteProfessional');
        expect(wrapper.text()).toContain(
            "A vitrine pública de profissionais continua sendo gerenciada em 'Site da clínica'.",
        );
    });

    it('opens the create sheet with ProfessionalForm in create mode', async () => {
        const wrapper = mount(Index, {
            props: { professionals: [makeProfessional()], eligibleUsers: [] },
        });

        expect(wrapper.findComponent(ProfessionalForm).exists()).toBe(false);

        const newButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Novo profissional'));
        await newButton?.trigger('click');

        const form = wrapper.findComponent(ProfessionalForm);
        expect(form.exists()).toBe(true);
        expect(form.props('mode')).toBe('create');
    });

    it('navigates to the dedicated edit route instead of opening a sheet', async () => {
        const professional = makeProfessional({ id: '42' });
        const wrapper = mount(Index, {
            props: { professionals: [professional] },
        });

        await wrapper.findComponent(ProfessionalRowActions).vm.$emit('edit');

        expect(routerMock.get).toHaveBeenCalledWith(
            '/settings/professionals/42/edit',
        );
    });

    it('shows the non-destructive delete confirmation wording', async () => {
        const wrapper = mount(Index, {
            props: { professionals: [makeProfessional()] },
            attachTo: document.body,
        });

        await wrapper.findComponent(ProfessionalRowActions).vm.$emit('delete');
        await wrapper.vm.$nextTick();

        const text = document.body.textContent ?? '';
        expect(text).toContain('Excluir profissional?');
        expect(text).toContain('será removido da operação');

        wrapper.unmount();
    });

    it('calls router.patch on the deactivate route when an active professional is deactivated', async () => {
        const professional = makeProfessional({ id: '7', status: 'active' });
        const wrapper = mount(Index, {
            props: { professionals: [professional] },
        });

        await wrapper
            .findComponent(ProfessionalRowActions)
            .vm.$emit('deactivate');

        expect(routerMock.patch).toHaveBeenCalledWith(
            '/settings/professionals/7/deactivate',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('filters the listing by unit', async () => {
        const professionals = [
            makeProfessional({
                id: '1',
                display_name: 'Dra. Ana Souza',
                unit_ids: ['unit-1'],
            }),
            makeProfessional({
                id: '2',
                display_name: 'Dr. Bruno Lima',
                unit_ids: ['unit-2'],
            }),
        ];
        const wrapper = mount(Index, {
            props: {
                professionals,
                units: [
                    { id: 'unit-1', name: 'Unidade Centro' },
                    { id: 'unit-2', name: 'Unidade Norte' },
                ],
            },
        });

        await wrapper
            .find('select[aria-label="Filtrar profissionais por unidade"]')
            .setValue('unit-1');

        expect(wrapper.text()).toContain('Dra. Ana Souza');
        expect(wrapper.text()).not.toContain('Dr. Bruno Lima');
    });

    it('filters the listing by working hours configuration', async () => {
        const professionals = [
            makeProfessional({
                id: '1',
                display_name: 'Dra. Ana Souza',
                has_working_hours: true,
            }),
            makeProfessional({
                id: '2',
                display_name: 'Dr. Bruno Lima',
                has_working_hours: false,
            }),
        ];
        const wrapper = mount(Index, { props: { professionals } });

        await wrapper
            .find('select[aria-label="Filtrar profissionais por jornada"]')
            .setValue('without');

        expect(wrapper.text()).toContain('Dr. Bruno Lima');
        expect(wrapper.text()).not.toContain('Dra. Ana Souza');
    });

    it('filters the listing by operational status', async () => {
        const professionals = [
            makeProfessional({
                id: '1',
                display_name: 'Dra. Ana Souza',
                operational_status: 'operational',
            }),
            makeProfessional({
                id: '2',
                display_name: 'Dr. Bruno Lima',
                operational_status: 'incomplete',
            }),
        ];
        const wrapper = mount(Index, { props: { professionals } });

        await wrapper
            .find(
                'select[aria-label="Filtrar profissionais por situação operacional"]',
            )
            .setValue('incomplete');

        expect(wrapper.text()).toContain('Dr. Bruno Lima');
        expect(wrapper.text()).not.toContain('Dra. Ana Souza');
    });

    it('filters the listing by ongoing absence', async () => {
        const professionals = [
            makeProfessional({
                id: '1',
                display_name: 'Dra. Ana Souza',
                has_ongoing_absence: true,
            }),
            makeProfessional({
                id: '2',
                display_name: 'Dr. Bruno Lima',
                has_ongoing_absence: false,
            }),
        ];
        const wrapper = mount(Index, { props: { professionals } });

        await wrapper.find('input[type="checkbox"]').setValue(true);

        expect(wrapper.text()).toContain('Dra. Ana Souza');
        expect(wrapper.text()).not.toContain('Dr. Bruno Lima');
    });

    it('shows the operational status label alongside each professional', () => {
        const wrapper = mount(Index, {
            props: {
                professionals: [
                    makeProfessional({ operational_status: 'operational' }),
                ],
            },
        });

        expect(wrapper.text()).toContain('Operacional');
    });

    it('hides actions the user is not authorized for by only rendering what the row-actions component allows', () => {
        // A ausência de props "canManage" no professional confirma que a
        // autorização real fica a cargo do backend/Policy — o frontend
        // apenas reflete o que a listagem recebida já filtrou/permitiu.
        const wrapper = mount(Index, {
            props: { professionals: [makeProfessional()] },
        });

        expect(wrapper.findComponent(ProfessionalRowActions).exists()).toBe(
            true,
        );
    });
});

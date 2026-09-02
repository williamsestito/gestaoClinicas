import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type { EditableUnit } from '@/components/units/UnitForm.vue';
import UnitForm from '@/components/units/UnitForm.vue';
import UnitRowActions from '@/components/units/UnitRowActions.vue';
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

function makeUnit(overrides: Partial<EditableUnit> = {}): EditableUnit {
    return {
        id: overrides.id ?? '1',
        organization_id: 'org-1',
        legal_entity_id: 'le-1',
        name: 'Unidade Centro',
        code: 'UN-0001',
        slug: 'unidade-centro',
        status: 'active',
        is_headquarters: false,
        timezone: 'America/Sao_Paulo',
        email: null,
        phone: null,
        whatsapp: null,
        deleted_at: null,
        address: {
            street: 'Rua A',
            number: '10',
            city: 'São Paulo',
            state: 'SP',
        },
        opening_hours: [],
        ...overrides,
    };
}

describe('settings/units/Index', () => {
    it('shows an empty state with a call to action when there are no units', () => {
        const wrapper = mount(Index, { props: { units: [], states: ['SP'] } });

        expect(wrapper.text()).toContain('Nenhuma unidade cadastrada ainda.');
        expect(wrapper.text()).toContain('Cadastrar primeira unidade');
    });

    it('shows correct indicator counts for total, active, inactive and deleted units', () => {
        const units = [
            makeUnit({ id: '1', status: 'active' }),
            makeUnit({ id: '2', status: 'active' }),
            makeUnit({ id: '3', status: 'inactive' }),
            makeUnit({ id: '4', deleted_at: '2026-07-19T12:00:00Z' }),
        ];
        const wrapper = mount(Index, { props: { units, states: ['SP'] } });

        const cards = wrapper.findAll('.text-2xl.font-semibold');
        expect(cards.map((card) => card.text())).toEqual(['4', '2', '1', '1']);
    });

    it('filters the listing by search term matching name or code', async () => {
        const units = [
            makeUnit({ id: '1', name: 'Unidade Centro', code: 'UN-0001' }),
            makeUnit({ id: '2', name: 'Unidade Norte', code: 'UN-0002' }),
        ];
        const wrapper = mount(Index, { props: { units, states: ['SP'] } });

        await wrapper
            .find('input[aria-label="Buscar unidades por nome ou código"]')
            .setValue('Norte');

        expect(wrapper.text()).toContain('Unidade Norte');
        expect(wrapper.text()).not.toContain('Unidade Centro');
    });

    it('filters the listing by status', async () => {
        const units = [
            makeUnit({ id: '1', name: 'Unidade Ativa', status: 'active' }),
            makeUnit({ id: '2', name: 'Unidade Inativa', status: 'inactive' }),
        ];
        const wrapper = mount(Index, { props: { units, states: ['SP'] } });

        await wrapper
            .find('select[aria-label="Filtrar unidades por status"]')
            .setValue('inactive');

        expect(wrapper.text()).toContain('Unidade Inativa');
        expect(wrapper.text()).not.toContain('Unidade Ativa');
    });

    it('shows a distinct message when filters match no unit, without hiding the whole page', async () => {
        const units = [makeUnit({ id: '1', name: 'Unidade Centro' })];
        const wrapper = mount(Index, { props: { units, states: ['SP'] } });

        await wrapper
            .find('input[aria-label="Buscar unidades por nome ou código"]')
            .setValue('Não existe');

        expect(wrapper.text()).toContain(
            'Nenhuma unidade corresponde aos filtros informados.',
        );
    });

    it('opens the create sheet with UnitForm in create mode when "Nova unidade" is clicked', async () => {
        const wrapper = mount(Index, {
            props: { units: [makeUnit()], states: ['SP'] },
        });

        expect(wrapper.findComponent(UnitForm).exists()).toBe(false);

        const newUnitButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Nova unidade'));
        await newUnitButton?.trigger('click');

        const form = wrapper.findComponent(UnitForm);
        expect(form.exists()).toBe(true);
        expect(form.props('mode')).toBe('create');
    });

    it('opens the edit sheet with the selected unit when "Editar" is triggered', async () => {
        const unit = makeUnit({ id: '42', name: 'Unidade Alvo' });
        const wrapper = mount(Index, {
            props: { units: [unit], states: ['SP'] },
        });

        await wrapper.findComponent(UnitRowActions).vm.$emit('edit');

        const form = wrapper.findComponent(UnitForm);
        expect(form.exists()).toBe(true);
        expect(form.props('mode')).toBe('edit');
        expect((form.props('unit') as EditableUnit).id).toBe('42');
    });

    it('shows the non-destructive delete confirmation wording when "Excluir" is triggered', async () => {
        const wrapper = mount(Index, {
            props: { units: [makeUnit()], states: ['SP'] },
            attachTo: document.body,
        });

        await wrapper.findComponent(UnitRowActions).vm.$emit('delete');
        await wrapper.vm.$nextTick();

        // O conteúdo do Dialog é renderizado via Teleport para document.body.
        const text = document.body.textContent ?? '';
        expect(text).toContain('Excluir unidade?');
        expect(text).toContain('será removido da operação');
        expect(text).toContain('poderá restaurá-lo depois');

        wrapper.unmount();
    });

    it('calls router.patch with the toggled status when "toggle-status" is emitted', async () => {
        const unit = makeUnit({ id: '7', status: 'active' });
        const wrapper = mount(Index, {
            props: { units: [unit], states: ['SP'] },
        });

        await wrapper.findComponent(UnitRowActions).vm.$emit('toggleStatus');

        expect(routerMock.patch).toHaveBeenCalledWith(
            '/settings/units/7/status',
            { active: false },
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});

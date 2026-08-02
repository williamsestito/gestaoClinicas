import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type { EditableSpecialty } from '@/components/specialties/SpecialtyForm.vue';
import SpecialtyForm from '@/components/specialties/SpecialtyForm.vue';
import SpecialtyRowActions from '@/components/specialties/SpecialtyRowActions.vue';
import Index from './Index.vue';
import type { SpecialtyRow } from './Index.vue';

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

function makeSpecialty(overrides: Partial<SpecialtyRow> = {}): SpecialtyRow {
    return {
        id: overrides.id ?? '1',
        name: 'Cardiologia',
        code: 'CARDIO',
        description: null,
        display_order: 0,
        status: 'active',
        professionals_count: 0,
        services_count: 0,
        deleted_at: null,
        updated_at: '2026-08-02T12:00:00Z',
        ...overrides,
    };
}

describe('settings/specialties/Index', () => {
    it('shows an empty state with a call to action when there are no specialties', () => {
        const wrapper = mount(Index, { props: { specialties: [] } });

        expect(wrapper.text()).toContain(
            'Nenhuma especialidade cadastrada ainda.',
        );
        expect(wrapper.text()).toContain('Cadastrar primeira especialidade');
    });

    it('shows correct indicator counts for total, active, inactive and deleted specialties', () => {
        const specialties = [
            makeSpecialty({ id: '1', status: 'active' }),
            makeSpecialty({ id: '2', status: 'active' }),
            makeSpecialty({ id: '3', status: 'inactive' }),
            makeSpecialty({ id: '4', deleted_at: '2026-07-19T12:00:00Z' }),
        ];
        const wrapper = mount(Index, { props: { specialties } });

        const cards = wrapper.findAll('.text-2xl.font-semibold');
        expect(cards.map((card) => card.text())).toEqual(['4', '2', '1', '1']);
    });

    it('filters the listing by search term matching name or code', async () => {
        const specialties = [
            makeSpecialty({ id: '1', name: 'Cardiologia', code: 'CARDIO' }),
            makeSpecialty({ id: '2', name: 'Dermatologia', code: 'DERMA' }),
        ];
        const wrapper = mount(Index, { props: { specialties } });

        await wrapper
            .find(
                'input[aria-label="Buscar especialidades por nome ou código"]',
            )
            .setValue('Derma');

        expect(wrapper.text()).toContain('Dermatologia');
        expect(wrapper.text()).not.toContain('Cardiologia');
    });

    it('filters the listing by status and excludes deleted records by default', async () => {
        const specialties = [
            makeSpecialty({
                id: '1',
                name: 'Consulta Padrão',
                status: 'active',
            }),
            makeSpecialty({
                id: '2',
                name: 'Consulta Removida',
                deleted_at: '2026-07-19T12:00:00Z',
            }),
        ];
        const wrapper = mount(Index, { props: { specialties } });

        expect(wrapper.text()).not.toContain('Consulta Removida');

        await wrapper
            .find('select[aria-label="Filtrar especialidades por status"]')
            .setValue('deleted');

        expect(wrapper.text()).toContain('Consulta Removida');
        expect(wrapper.text()).not.toContain('Consulta Padrão');
    });

    it('opens the create sheet with SpecialtyForm in create mode', async () => {
        const wrapper = mount(Index, {
            props: { specialties: [makeSpecialty()] },
        });

        expect(wrapper.findComponent(SpecialtyForm).exists()).toBe(false);

        const newButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Nova especialidade'));
        await newButton?.trigger('click');

        const form = wrapper.findComponent(SpecialtyForm);
        expect(form.exists()).toBe(true);
        expect(form.props('mode')).toBe('create');
    });

    it('opens the edit sheet with the selected specialty', async () => {
        const specialty = makeSpecialty({ id: '42', name: 'Ortopedia' });
        const wrapper = mount(Index, {
            props: { specialties: [specialty] },
        });

        await wrapper.findComponent(SpecialtyRowActions).vm.$emit('edit');

        const form = wrapper.findComponent(SpecialtyForm);
        expect(form.exists()).toBe(true);
        expect(form.props('mode')).toBe('edit');
        expect((form.props('specialty') as EditableSpecialty).id).toBe('42');
    });

    it('shows the non-destructive delete confirmation wording when "Excluir" is triggered', async () => {
        const wrapper = mount(Index, {
            props: { specialties: [makeSpecialty()] },
            attachTo: document.body,
        });

        await wrapper.findComponent(SpecialtyRowActions).vm.$emit('delete');
        await wrapper.vm.$nextTick();

        const text = document.body.textContent ?? '';
        expect(text).toContain('Excluir especialidade?');
        expect(text).toContain('será removido da operação');
        expect(text).toContain('poderá restaurá-lo depois');

        wrapper.unmount();
    });

    it('calls router.patch on the deactivate route when an active specialty is deactivated', async () => {
        const specialty = makeSpecialty({ id: '7', status: 'active' });
        const wrapper = mount(Index, {
            props: { specialties: [specialty] },
        });

        await wrapper.findComponent(SpecialtyRowActions).vm.$emit('deactivate');

        expect(routerMock.patch).toHaveBeenCalledWith(
            '/settings/specialties/7/deactivate',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('calls router.post on the restore route for a deleted specialty', async () => {
        const specialty = makeSpecialty({
            id: '9',
            deleted_at: '2026-07-19T12:00:00Z',
        });
        const wrapper = mount(Index, {
            props: { specialties: [specialty] },
        });

        await wrapper
            .find('select[aria-label="Filtrar especialidades por status"]')
            .setValue('deleted');

        await wrapper.findComponent(SpecialtyRowActions).vm.$emit('restore');

        expect(routerMock.post).toHaveBeenCalledWith(
            '/settings/specialties/9/restore',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});

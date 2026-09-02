import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type { EditableLegalEntity } from '@/components/legal-entities/LegalEntityForm.vue';
import LegalEntityForm from '@/components/legal-entities/LegalEntityForm.vue';
import LegalEntityRowActions from '@/components/legal-entities/LegalEntityRowActions.vue';
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

const legalEntityTypes = [
    { value: 'individual', label: 'Pessoa física (CPF)' },
    { value: 'company', label: 'Pessoa jurídica (CNPJ)' },
];

function makeLegalEntity(
    overrides: Partial<EditableLegalEntity> = {},
): EditableLegalEntity {
    return {
        id: overrides.id ?? '1',
        organization_id: 'org-1',
        type: 'company',
        document: '**.***.***/0001-**',
        legal_name: 'Clínica Exemplo LTDA',
        trade_name: 'Clínica Exemplo',
        is_primary: false,
        status: 'active',
        deleted_at: null,
        address: {
            street: 'Rua A',
            number: '10',
            city: 'São Paulo',
            state: 'SP',
        },
        ...overrides,
    };
}

describe('settings/legal-entities/Index', () => {
    it('shows an empty state with a call to action when there are no legal entities', () => {
        const wrapper = mount(Index, {
            props: { legalEntities: [], legalEntityTypes, states: ['SP'] },
        });

        expect(wrapper.text()).toContain(
            'Nenhuma entidade legal cadastrada ainda.',
        );
        expect(wrapper.text()).toContain('Cadastrar primeira entidade legal');
    });

    it('shows correct indicator counts for total, active, inactive and deleted entities', () => {
        const legalEntities = [
            makeLegalEntity({ id: '1', status: 'active' }),
            makeLegalEntity({ id: '2', status: 'active' }),
            makeLegalEntity({ id: '3', status: 'inactive' }),
            makeLegalEntity({ id: '4', deleted_at: '2026-07-19T12:00:00Z' }),
        ];
        const wrapper = mount(Index, {
            props: { legalEntities, legalEntityTypes, states: ['SP'] },
        });

        const cards = wrapper.findAll('.text-2xl.font-semibold');
        expect(cards.map((card) => card.text())).toEqual(['4', '2', '1', '1']);
    });

    it('filters the listing by search term matching name or document', async () => {
        const legalEntities = [
            makeLegalEntity({ id: '1', legal_name: 'Clínica Norte' }),
            makeLegalEntity({ id: '2', legal_name: 'Clínica Sul' }),
        ];
        const wrapper = mount(Index, {
            props: { legalEntities, legalEntityTypes, states: ['SP'] },
        });

        await wrapper
            .find(
                'input[aria-label="Buscar entidades legais por nome ou documento"]',
            )
            .setValue('Sul');

        expect(wrapper.text()).toContain('Clínica Sul');
        expect(wrapper.text()).not.toContain('Clínica Norte');
    });

    it('shows a distinct message when filters match no legal entity', async () => {
        const legalEntities = [makeLegalEntity({ legal_name: 'Clínica X' })];
        const wrapper = mount(Index, {
            props: { legalEntities, legalEntityTypes, states: ['SP'] },
        });

        await wrapper
            .find(
                'input[aria-label="Buscar entidades legais por nome ou documento"]',
            )
            .setValue('Não existe');

        expect(wrapper.text()).toContain(
            'Nenhuma entidade legal corresponde aos filtros informados.',
        );
    });

    it('opens the create sheet with LegalEntityForm in create mode', async () => {
        const wrapper = mount(Index, {
            props: {
                legalEntities: [makeLegalEntity()],
                legalEntityTypes,
                states: ['SP'],
            },
        });

        expect(wrapper.findComponent(LegalEntityForm).exists()).toBe(false);

        const newButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Nova entidade legal'));
        await newButton?.trigger('click');

        const form = wrapper.findComponent(LegalEntityForm);
        expect(form.exists()).toBe(true);
        expect(form.props('mode')).toBe('create');
    });

    it('opens the edit sheet with the selected legal entity', async () => {
        const entity = makeLegalEntity({ id: '42', legal_name: 'Alvo' });
        const wrapper = mount(Index, {
            props: {
                legalEntities: [entity],
                legalEntityTypes,
                states: ['SP'],
            },
        });

        await wrapper.findComponent(LegalEntityRowActions).vm.$emit('edit');

        const form = wrapper.findComponent(LegalEntityForm);
        expect(form.exists()).toBe(true);
        expect(form.props('mode')).toBe('edit');
        expect((form.props('legalEntity') as EditableLegalEntity).id).toBe(
            '42',
        );
    });

    it('shows the non-destructive delete confirmation wording when "Excluir" is triggered', async () => {
        const wrapper = mount(Index, {
            props: {
                legalEntities: [makeLegalEntity()],
                legalEntityTypes,
                states: ['SP'],
            },
            attachTo: document.body,
        });

        await wrapper.findComponent(LegalEntityRowActions).vm.$emit('delete');
        await wrapper.vm.$nextTick();

        const text = document.body.textContent ?? '';
        expect(text).toContain('Excluir entidade legal?');
        expect(text).toContain('será removido da operação');
        expect(text).toContain('poderá restaurá-lo depois');

        wrapper.unmount();
    });

    it('calls router.patch with the toggled status when "toggle-status" is emitted', async () => {
        const entity = makeLegalEntity({ id: '7', status: 'active' });
        const wrapper = mount(Index, {
            props: {
                legalEntities: [entity],
                legalEntityTypes,
                states: ['SP'],
            },
        });

        await wrapper
            .findComponent(LegalEntityRowActions)
            .vm.$emit('toggleStatus');

        expect(routerMock.patch).toHaveBeenCalledWith(
            '/settings/legal-entities/7/status',
            { active: false },
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});

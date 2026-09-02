import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import AddressFields from '@/components/organization/AddressFields.vue';
import OpeningHoursFields from '@/components/organization/OpeningHoursFields.vue';
import type { EditableUnit } from './UnitForm.vue';
import UnitForm from './UnitForm.vue';

type MockFormOptions = { onSuccess?: () => void };

const createdForms: Record<string, unknown>[] = [];

function createMockForm(initial: Record<string, unknown>) {
    const form = reactive({
        ...initial,
        errors: {} as Record<string, string>,
        processing: false,
        post: vi.fn((_url: string, options?: MockFormOptions) => {
            options?.onSuccess?.();
        }),
        put: vi.fn((_url: string, options?: MockFormOptions) => {
            options?.onSuccess?.();
        }),
    });
    createdForms.push(form);

    return form;
}

vi.mock('@inertiajs/vue3', () => ({
    useForm: (initial: Record<string, unknown>) => createMockForm(initial),
}));

// UnitForm chama useForm() duas vezes, sempre nesta ordem (createForm,
// depois editForm), independentemente do modo — por isso os índices fixos.
function lastCreateForm() {
    return createdForms[createdForms.length - 2] as {
        post: ReturnType<typeof vi.fn>;
        errors: Record<string, string>;
    };
}

function lastEditForm() {
    return createdForms[createdForms.length - 1] as {
        put: ReturnType<typeof vi.fn>;
        errors: Record<string, string>;
    };
}

const unit: EditableUnit = {
    id: 'unit-1',
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
    address: { street: 'Rua A', number: '10', city: 'São Paulo', state: 'SP' },
    opening_hours: [{ day_of_week: 1, opens_at: '08:00', closes_at: '18:00' }],
};

describe('UnitForm', () => {
    beforeEach(() => {
        createdForms.length = 0;
    });

    it('create mode renders the address and opening hours fields, editable', () => {
        const wrapper = mount(UnitForm, {
            props: { mode: 'create', states: ['SP'] },
        });

        expect(wrapper.findComponent(AddressFields).exists()).toBe(true);
        expect(wrapper.findComponent(OpeningHoursFields).exists()).toBe(true);
        expect(wrapper.text()).toContain('Criar unidade');
    });

    it('edit mode never renders editable address/opening-hours fields, only a read-only summary', () => {
        const wrapper = mount(UnitForm, {
            props: { mode: 'edit', unit },
        });

        expect(wrapper.findComponent(AddressFields).exists()).toBe(false);
        expect(wrapper.findComponent(OpeningHoursFields).exists()).toBe(false);
        expect(wrapper.text()).toContain('Rua A, 10');
        expect(wrapper.text()).toContain('Salvar alterações');
    });

    it('edit mode shows the unit code as read-only text, not an editable field', () => {
        const wrapper = mount(UnitForm, {
            props: { mode: 'edit', unit },
        });

        expect(wrapper.find('#unit-code').exists()).toBe(false);
        expect(wrapper.text()).toContain('Código: UN-0001');
    });

    it('submits to the store endpoint in create mode and emits success', async () => {
        const wrapper = mount(UnitForm, {
            props: { mode: 'create', states: ['SP'] },
        });

        await wrapper.find('form').trigger('submit.prevent');

        expect(lastCreateForm().post).toHaveBeenCalledWith(
            '/settings/units',
            expect.objectContaining({ onSuccess: expect.any(Function) }),
        );
        expect(wrapper.emitted('success')).toBeTruthy();
    });

    it('submits to the update endpoint in edit mode and emits success', async () => {
        const wrapper = mount(UnitForm, {
            props: { mode: 'edit', unit },
        });

        await wrapper.find('form').trigger('submit.prevent');

        expect(lastEditForm().put).toHaveBeenCalledWith(
            `/settings/units/${unit.id}`,
            expect.objectContaining({ onSuccess: expect.any(Function) }),
        );
        expect(wrapper.emitted('success')).toBeTruthy();
    });

    it('emits cancel when the cancel button is clicked', async () => {
        const wrapper = mount(UnitForm, {
            props: { mode: 'create', states: ['SP'] },
        });

        const cancelButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Cancelar');
        await cancelButton?.trigger('click');

        expect(wrapper.emitted('cancel')).toBeTruthy();
    });

    it('shows validation errors next to the corresponding field', async () => {
        const wrapper = mount(UnitForm, {
            props: { mode: 'create', states: ['SP'] },
        });

        lastCreateForm().errors.name = 'O nome é obrigatório.';
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('O nome é obrigatório.');
    });
});

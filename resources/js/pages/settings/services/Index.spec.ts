import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import ServiceForm from '@/components/services/ServiceForm.vue';
import ServiceRowActions from '@/components/services/ServiceRowActions.vue';
import Index from './Index.vue';
import type { ServiceRow } from './Index.vue';

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

function makeService(overrides: Partial<ServiceRow> = {}): ServiceRow {
    return {
        id: overrides.id ?? '1',
        name: 'Consulta Padrão',
        code: 'CONS-01',
        default_duration_minutes: 30,
        default_price_cents: 15000,
        status: 'active',
        is_public: false,
        unit_availability_scope: 'all_units',
        specialties: [],
        professionals_count: 0,
        deleted_at: null,
        updated_at: '2026-08-02T12:00:00Z',
        ...overrides,
    };
}

describe('settings/services/Index', () => {
    it('shows an empty state with a call to action when there are no services', () => {
        const wrapper = mount(Index, { props: { services: [] } });

        expect(wrapper.text()).toContain('Nenhum serviço cadastrado ainda.');
        expect(wrapper.text()).toContain('Cadastrar primeiro serviço');
    });

    it('formats the price in pt-BR currency and the duration in hours/minutes', () => {
        const services = [
            makeService({
                default_duration_minutes: 90,
                default_price_cents: 12345,
            }),
        ];
        const wrapper = mount(Index, { props: { services } });

        expect(wrapper.text()).toContain('1h30min');
        expect(wrapper.text()).toContain('R$');
        expect(wrapper.text()).toContain('123,45');
    });

    it('shows a dash for services without a default price', () => {
        const services = [makeService({ default_price_cents: null })];
        const wrapper = mount(Index, { props: { services } });

        expect(wrapper.text()).toContain('—');
    });

    it('filters the listing by search term matching name or code', async () => {
        const services = [
            makeService({ id: '1', name: 'Consulta Padrão', code: 'CONS-01' }),
            makeService({ id: '2', name: 'Avaliação Física', code: 'AVAL-01' }),
        ];
        const wrapper = mount(Index, { props: { services } });

        await wrapper
            .find('input[aria-label="Buscar serviços por nome ou código"]')
            .setValue('Avaliação');

        expect(wrapper.text()).toContain('Avaliação Física');
        expect(wrapper.text()).not.toContain('Consulta Padrão');
    });

    it('never mixes SiteService content into the operational listing', () => {
        const wrapper = mount(Index, {
            props: { services: [makeService()] },
        });

        expect(wrapper.text()).not.toContain('SiteService');
        expect(wrapper.text()).toContain(
            "O conteúdo público do site continua sendo gerenciado em 'Site da clínica'.",
        );
    });

    it('opens the create sheet with ServiceForm in create mode', async () => {
        const wrapper = mount(Index, {
            props: { services: [makeService()], specialties: [], units: [] },
        });

        expect(wrapper.findComponent(ServiceForm).exists()).toBe(false);

        const newButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Novo serviço'));
        await newButton?.trigger('click');

        const form = wrapper.findComponent(ServiceForm);
        expect(form.exists()).toBe(true);
        expect(form.props('mode')).toBe('create');
    });

    it('navigates to the dedicated edit route instead of opening a sheet', async () => {
        const service = makeService({ id: '42' });
        const wrapper = mount(Index, { props: { services: [service] } });

        await wrapper.findComponent(ServiceRowActions).vm.$emit('edit');

        expect(routerMock.get).toHaveBeenCalledWith(
            '/settings/services/42/edit',
        );
    });

    it('shows the non-destructive delete confirmation wording', async () => {
        const wrapper = mount(Index, {
            props: { services: [makeService()] },
            attachTo: document.body,
        });

        await wrapper.findComponent(ServiceRowActions).vm.$emit('delete');
        await wrapper.vm.$nextTick();

        const text = document.body.textContent ?? '';
        expect(text).toContain('Excluir serviço?');
        expect(text).toContain('será removido da operação');

        wrapper.unmount();
    });

    it('calls router.patch on the deactivate route when an active service is deactivated', async () => {
        const service = makeService({ id: '7', status: 'active' });
        const wrapper = mount(Index, { props: { services: [service] } });

        await wrapper.findComponent(ServiceRowActions).vm.$emit('deactivate');

        expect(routerMock.patch).toHaveBeenCalledWith(
            '/settings/services/7/deactivate',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});

import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type { SiteService } from '@/types/site';
import Index from './Index.vue';

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

function makeService(overrides: Partial<SiteService> = {}): SiteService {
    return {
        id: overrides.id ?? 1,
        name: 'Limpeza de pele',
        short_description: null,
        description: null,
        image_url: null,
        icon: null,
        category: null,
        duration_minutes: null,
        starting_price_cents: null,
        cta_text: null,
        is_featured: false,
        order: 0,
        is_active: true,
        service_id: null,
        linked_service: null,
        ...overrides,
    };
}

describe('settings/site/services/Index', () => {
    it('shows a select to link an operational service when unlinked', () => {
        const wrapper = mount(Index, {
            props: {
                services: [makeService()],
                operationalServices: [
                    { id: 'service-1', name: 'Consulta Padrão' },
                ],
            },
        });

        expect(
            wrapper
                .find(
                    'select[aria-label="Vincular Limpeza de pele a um serviço operacional"]',
                )
                .exists(),
        ).toBe(true);
    });

    it('shows the linked service and an unlink action', () => {
        const wrapper = mount(Index, {
            props: {
                services: [
                    makeService({
                        service_id: 'service-1',
                        linked_service: {
                            id: 'service-1',
                            name: 'Consulta Padrão',
                            is_operational: true,
                        },
                    }),
                ],
            },
        });

        expect(wrapper.text()).toContain('Vinculado a');
        expect(wrapper.text()).toContain('Consulta Padrão');
    });

    it('shows an operational-inactive alert without hiding the promotional content', () => {
        const wrapper = mount(Index, {
            props: {
                services: [
                    makeService({
                        service_id: 'service-1',
                        linked_service: {
                            id: 'service-1',
                            name: 'Consulta Padrão',
                            is_operational: false,
                        },
                    }),
                ],
            },
        });

        expect(wrapper.text()).toContain(
            'Este serviço está inativo e não será exibido publicamente.',
        );
        expect(wrapper.text()).toContain('Limpeza de pele');
    });

    it('calls router.delete on the link route when unlinking', async () => {
        const wrapper = mount(Index, {
            props: {
                services: [
                    makeService({
                        id: 7,
                        service_id: 'service-1',
                        linked_service: {
                            id: 'service-1',
                            name: 'Consulta Padrão',
                            is_operational: true,
                        },
                    }),
                ],
            },
        });

        const unlinkButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Desvincular'));
        await unlinkButton?.trigger('click');

        expect(routerMock.delete).toHaveBeenCalledWith(
            '/settings/site/services/7/link',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('requires an explicit opt-in checkbox to include price in the copy dialog', async () => {
        const wrapper = mount(Index, {
            attachTo: document.body,
            props: {
                services: [
                    makeService({
                        service_id: 'service-1',
                        linked_service: {
                            id: 'service-1',
                            name: 'Consulta Padrão',
                            is_operational: true,
                        },
                    }),
                ],
            },
        });

        const copyButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Copiar dados públicos'));
        await copyButton?.trigger('click');
        await wrapper.vm.$nextTick();

        const text = document.body.textContent ?? '';
        expect(text).toContain('Preço público');
        expect(text).toContain('nunca por padrão');

        wrapper.unmount();
    });
});

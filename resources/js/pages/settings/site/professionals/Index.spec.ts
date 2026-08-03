import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type { SiteProfessional } from '@/types/site';
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

function makeProfessional(
    overrides: Partial<SiteProfessional> = {},
): SiteProfessional {
    return {
        id: overrides.id ?? 1,
        name: 'Dra. Ana Souza',
        role_title: null,
        specialty: null,
        professional_register: null,
        bio: null,
        photo_url: null,
        facebook_url: null,
        instagram_url: null,
        linkedin_url: null,
        order: 0,
        is_active: true,
        professional_id: null,
        linked_professional: null,
        ...overrides,
    };
}

describe('settings/site/professionals/Index', () => {
    it('shows a select to link an operational professional when unlinked', () => {
        const wrapper = mount(Index, {
            props: {
                professionals: [makeProfessional()],
                operationalProfessionals: [{ id: 'prof-1', name: 'Dr. João' }],
            },
        });

        expect(
            wrapper
                .find(
                    'select[aria-label="Vincular Dra. Ana Souza a um profissional operacional"]',
                )
                .exists(),
        ).toBe(true);
        expect(wrapper.text()).toContain(
            'O conteúdo público acima continua sendo gerenciado de forma independente',
        );
    });

    it('shows the linked professional and an unlink action', () => {
        const wrapper = mount(Index, {
            props: {
                professionals: [
                    makeProfessional({
                        professional_id: 'prof-1',
                        linked_professional: {
                            id: 'prof-1',
                            name: 'Dr. João',
                            is_operational: true,
                        },
                    }),
                ],
            },
        });

        expect(wrapper.text()).toContain('Vinculado a');
        expect(wrapper.text()).toContain('Dr. João');
        expect(wrapper.text()).not.toContain('Este profissional está inativo');
    });

    it('shows an operational-inactive alert without hiding the promotional content', () => {
        const wrapper = mount(Index, {
            props: {
                professionals: [
                    makeProfessional({
                        professional_id: 'prof-1',
                        linked_professional: {
                            id: 'prof-1',
                            name: 'Dr. João',
                            is_operational: false,
                        },
                    }),
                ],
            },
        });

        expect(wrapper.text()).toContain(
            'Este profissional está inativo e não será exibido publicamente.',
        );
        expect(wrapper.text()).toContain('Dra. Ana Souza');
    });

    it('calls router.delete on the link route when unlinking', async () => {
        const wrapper = mount(Index, {
            props: {
                professionals: [
                    makeProfessional({
                        id: 7,
                        professional_id: 'prof-1',
                        linked_professional: {
                            id: 'prof-1',
                            name: 'Dr. João',
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
            '/settings/site/professionals/7/link',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('calls router.post on the link route with the selected professional id', async () => {
        const wrapper = mount(Index, {
            props: {
                professionals: [makeProfessional({ id: 9 })],
                operationalProfessionals: [{ id: 'prof-1', name: 'Dr. João' }],
            },
        });

        await wrapper
            .find(
                'select[aria-label="Vincular Dra. Ana Souza a um profissional operacional"]',
            )
            .setValue('prof-1');

        const linkButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Vincular'));
        await linkButton?.trigger('click');

        expect(routerMock.post).toHaveBeenCalledWith(
            '/settings/site/professionals/9/link',
            { professional_id: 'prof-1' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('opens the copy-data dialog only offering the allowed fields', async () => {
        const wrapper = mount(Index, {
            attachTo: document.body,
            props: {
                professionals: [
                    makeProfessional({
                        professional_id: 'prof-1',
                        linked_professional: {
                            id: 'prof-1',
                            name: 'Dr. João',
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
        expect(text).toContain('Nome');
        expect(text).toContain('Biografia');
        expect(text).not.toContain('Documento');
        expect(text).not.toContain('E-mail');

        wrapper.unmount();
    });
});

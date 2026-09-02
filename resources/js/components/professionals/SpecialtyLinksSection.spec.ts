import { DOMWrapper, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type {
    SpecialtyLink,
    SpecialtyOption,
} from './SpecialtyLinksSection.vue';
import SpecialtyLinksSection from './SpecialtyLinksSection.vue';

const { routerMock } = vi.hoisted(() => ({
    routerMock: {
        patch: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    router: routerMock,
    useForm: (initial: Record<string, unknown>) =>
        reactive({
            ...initial,
            errors: {},
            processing: false,
            post: vi.fn(),
        }),
}));

const cardiology: SpecialtyLink = {
    id: 'link-1',
    specialty: { id: 'sp-1', name: 'Cardiologia' },
    is_primary: true,
    status: 'active',
    deleted_at: null,
};

const dermatology: SpecialtyLink = {
    id: 'link-2',
    specialty: { id: 'sp-2', name: 'Dermatologia' },
    is_primary: false,
    status: 'active',
    deleted_at: null,
};

const eligible: SpecialtyOption[] = [{ id: 'sp-3', name: 'Ortopedia' }];

function mountAttached(props: {
    professionalId: string;
    links: SpecialtyLink[];
    eligibleSpecialties: SpecialtyOption[];
}) {
    return mount(SpecialtyLinksSection, { props, attachTo: document.body });
}

async function openMenu(wrapper: ReturnType<typeof mountAttached>) {
    await wrapper.find('button[aria-label^="Ações para"]').trigger('click');
    await new Promise((resolve) => setTimeout(resolve, 0));
}

async function clickMenuItem(text: string) {
    const item = Array.from(
        document.body.querySelectorAll('[role="menuitem"]'),
    ).find((element) => element.textContent?.trim() === text);
    await new DOMWrapper(item as Element).trigger('click');
    await new Promise((resolve) => setTimeout(resolve, 0));
}

describe('SpecialtyLinksSection', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('shows an empty state message when there are no specialty links', () => {
        const wrapper = mount(SpecialtyLinksSection, {
            props: {
                professionalId: 'prof-1',
                links: [],
                eligibleSpecialties: eligible,
            },
        });

        expect(wrapper.text()).toContain(
            'Nenhuma especialidade vinculada ainda.',
        );
    });

    it('shows the "Principal" badge only for the primary link', () => {
        const wrapper = mount(SpecialtyLinksSection, {
            props: {
                professionalId: 'prof-1',
                links: [cardiology, dermatology],
                eligibleSpecialties: [],
            },
        });

        const items = wrapper.findAll('li');
        expect(items[0].text()).toContain('Principal');
        expect(items[1].text()).not.toContain('Principal');
    });

    it('disables the "Adicionar" button until a specialty is selected', async () => {
        const wrapper = mount(SpecialtyLinksSection, {
            props: {
                professionalId: 'prof-1',
                links: [],
                eligibleSpecialties: eligible,
            },
        });

        const addButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Adicionar');
        expect(addButton?.attributes('disabled')).toBeDefined();

        await wrapper.find('#specialty-select').setValue('sp-3');
        expect(addButton?.attributes('disabled')).toBeUndefined();
    });

    it('hides the add form and shows a message when there are no eligible specialties', () => {
        const wrapper = mount(SpecialtyLinksSection, {
            props: {
                professionalId: 'prof-1',
                links: [cardiology],
                eligibleSpecialties: [],
            },
        });

        expect(wrapper.find('#specialty-select').exists()).toBe(false);
        expect(wrapper.text()).toContain(
            'Não há especialidades ativas disponíveis para adicionar.',
        );
    });

    it('calls router.patch against the primary route when "Definir como principal" is selected', async () => {
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            links: [dermatology],
            eligibleSpecialties: [],
        });

        await openMenu(wrapper);
        await clickMenuItem('Definir como principal');

        expect(routerMock.patch).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/specialties/link-2/primary',
            ),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('calls router.patch against the deactivate route for an active link', async () => {
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            links: [dermatology],
            eligibleSpecialties: [],
        });

        await openMenu(wrapper);
        await clickMenuItem('Inativar');

        expect(routerMock.patch).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/specialties/link-2/deactivate',
            ),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('calls router.patch against the activate route for an inactive link', async () => {
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            links: [{ ...dermatology, status: 'inactive' }],
            eligibleSpecialties: [],
        });

        await openMenu(wrapper);
        await clickMenuItem('Ativar');

        expect(routerMock.patch).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/specialties/link-2/activate',
            ),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows the non-destructive confirmation wording and only calls router.delete after confirming', async () => {
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            links: [dermatology],
            eligibleSpecialties: [],
        });

        await openMenu(wrapper);
        await clickMenuItem('Remover');

        const text = document.body.textContent ?? '';
        expect(text).toContain('Remover especialidade?');
        expect(text).toContain('será removido da operação');
        expect(text).toContain('histórico será preservado');
        expect(routerMock.delete).not.toHaveBeenCalled();

        const confirmButton = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) => button.textContent?.trim() === 'Remover');
        await new DOMWrapper(confirmButton as Element).trigger('click');

        expect(routerMock.delete).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/specialties/link-2',
            ),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows only "Restaurar" for a soft-deleted link and calls router.post on restore', async () => {
        const deleted: SpecialtyLink = {
            ...dermatology,
            deleted_at: '2026-07-19T12:00:00Z',
        };
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            links: [deleted],
            eligibleSpecialties: [],
        });

        await openMenu(wrapper);
        const text = document.body.textContent ?? '';
        expect(text).toContain('Restaurar');
        expect(text).not.toContain('Remover');

        await clickMenuItem('Restaurar');

        expect(routerMock.post).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/specialties/link-2/restore',
            ),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});

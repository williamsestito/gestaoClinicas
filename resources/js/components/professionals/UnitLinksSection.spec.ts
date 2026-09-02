import { DOMWrapper, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type { UnitLink, UnitOption } from './UnitLinksSection.vue';
import UnitLinksSection from './UnitLinksSection.vue';

const { routerMock } = vi.hoisted(() => ({
    routerMock: {
        patch: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
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
            put: vi.fn(),
            reset: vi.fn(),
        }),
}));

const centro: UnitLink = {
    id: 'link-1',
    unit: { id: 'unit-1', name: 'Unidade Centro' },
    is_primary: true,
    status: 'active',
    starts_on: null,
    ends_on: null,
    vigency_status: 'in_effect',
    deleted_at: null,
};

const norte: UnitLink = {
    id: 'link-2',
    unit: { id: 'unit-2', name: 'Unidade Norte' },
    is_primary: false,
    status: 'active',
    starts_on: '2026-09-01',
    ends_on: null,
    vigency_status: 'scheduled',
    deleted_at: null,
};

const eligible: UnitOption[] = [{ id: 'unit-3', name: 'Unidade Sul' }];

function mountAttached(props: {
    professionalId: string;
    links: UnitLink[];
    eligibleUnits: UnitOption[];
}) {
    return mount(UnitLinksSection, { props, attachTo: document.body });
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

describe('UnitLinksSection', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('shows an empty state message when there are no unit links', () => {
        const wrapper = mount(UnitLinksSection, {
            props: {
                professionalId: 'prof-1',
                links: [],
                eligibleUnits: eligible,
            },
        });

        expect(wrapper.text()).toContain('Nenhuma unidade vinculada ainda.');
    });

    it('shows the "Principal" badge only for the primary link', () => {
        const wrapper = mount(UnitLinksSection, {
            props: {
                professionalId: 'prof-1',
                links: [centro, norte],
                eligibleUnits: [],
            },
        });

        const items = wrapper.findAll('li');
        expect(items[0].text()).toContain('Principal');
        expect(items[1].text()).not.toContain('Principal');
    });

    it('shows the vigency status label for each link', () => {
        const wrapper = mount(UnitLinksSection, {
            props: {
                professionalId: 'prof-1',
                links: [centro, norte],
                eligibleUnits: [],
            },
        });

        expect(wrapper.text()).toContain('Vigente');
        expect(wrapper.text()).toContain('Agendado');
    });

    it('disables the "Adicionar" button until a unit is selected', async () => {
        const wrapper = mount(UnitLinksSection, {
            props: {
                professionalId: 'prof-1',
                links: [],
                eligibleUnits: eligible,
            },
        });

        const addButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Adicionar');
        expect(addButton?.attributes('disabled')).toBeDefined();

        await wrapper.find('#unit-select').setValue('unit-3');
        expect(addButton?.attributes('disabled')).toBeUndefined();
    });

    it('hides the add form and shows a message when there are no eligible units', () => {
        const wrapper = mount(UnitLinksSection, {
            props: {
                professionalId: 'prof-1',
                links: [centro],
                eligibleUnits: [],
            },
        });

        expect(wrapper.find('#unit-select').exists()).toBe(false);
        expect(wrapper.text()).toContain(
            'Não há unidades ativas disponíveis para adicionar.',
        );
    });

    it('calls router.patch against the primary route when "Definir como principal" is selected', async () => {
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            links: [norte],
            eligibleUnits: [],
        });

        await openMenu(wrapper);
        await clickMenuItem('Definir como principal');

        expect(routerMock.patch).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/units/link-2/primary',
            ),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('calls router.patch against the deactivate route for an active link', async () => {
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            links: [norte],
            eligibleUnits: [],
        });

        await openMenu(wrapper);
        await clickMenuItem('Inativar');

        expect(routerMock.patch).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/units/link-2/deactivate',
            ),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows the non-destructive confirmation wording and only calls router.delete after confirming', async () => {
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            links: [norte],
            eligibleUnits: [],
        });

        await openMenu(wrapper);
        await clickMenuItem('Remover');

        const text = document.body.textContent ?? '';
        expect(text).toContain('Remover unidade?');
        expect(text).toContain('será removido da operação');
        expect(text).toContain('histórico será preservado');
        expect(routerMock.delete).not.toHaveBeenCalled();

        const confirmButton = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) => button.textContent?.trim() === 'Remover');
        await new DOMWrapper(confirmButton as Element).trigger('click');

        expect(routerMock.delete).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/units/link-2',
            ),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows only "Restaurar" for a soft-deleted link and calls router.post on restore', async () => {
        const deleted: UnitLink = {
            ...norte,
            deleted_at: '2026-07-19T12:00:00Z',
        };
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            links: [deleted],
            eligibleUnits: [],
        });

        await openMenu(wrapper);
        const text = document.body.textContent ?? '';
        expect(text).toContain('Restaurar');
        expect(text).not.toContain('Remover');

        await clickMenuItem('Restaurar');

        expect(routerMock.post).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/units/link-2/restore',
            ),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('opens the edit vigency sheet with the current dates when "Editar vigência" is selected', async () => {
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            links: [norte],
            eligibleUnits: [],
        });

        await openMenu(wrapper);
        await clickMenuItem('Editar vigência');

        const startInput = document.body.querySelector(
            '#edit-unit-starts-on',
        ) as HTMLInputElement;
        expect(startInput.value).toBe('2026-09-01');
    });
});

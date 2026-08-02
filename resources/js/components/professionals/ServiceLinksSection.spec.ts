import { DOMWrapper, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type {
    ProfessionalUnitOption,
    ServiceOption,
} from './ServiceAssignmentForm.vue';
import type { ServiceLink } from './ServiceLinksSection.vue';
import ServiceLinksSection from './ServiceLinksSection.vue';

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
        }),
}));

const inheritedLink: ServiceLink = {
    id: 'link-1',
    service: { id: 'svc-1', name: 'Consulta' },
    status: 'active',
    unit_scope: 'all_compatible_units',
    selected_unit_ids: [],
    compatible_units: ['unit-1'],
    duration_minutes: {
        default: 30,
        custom: null,
        effective: 30,
        is_inherited: true,
    },
    price_cents: {
        default: 10000,
        custom: null,
        effective: 10000,
        is_inherited: true,
    },
    buffer_before_minutes: {
        default: 0,
        custom: null,
        effective: 0,
        is_inherited: true,
    },
    buffer_after_minutes: {
        default: 0,
        custom: null,
        effective: 0,
        is_inherited: true,
    },
    deleted_at: null,
};

const customLink: ServiceLink = {
    id: 'link-2',
    service: { id: 'svc-2', name: 'Retorno' },
    status: 'active',
    unit_scope: 'none',
    selected_unit_ids: [],
    compatible_units: [],
    duration_minutes: {
        default: 20,
        custom: 45,
        effective: 45,
        is_inherited: false,
    },
    price_cents: {
        default: null,
        custom: 5000,
        effective: 5000,
        is_inherited: false,
    },
    buffer_before_minutes: {
        default: 0,
        custom: 5,
        effective: 5,
        is_inherited: false,
    },
    buffer_after_minutes: {
        default: 0,
        custom: 5,
        effective: 5,
        is_inherited: false,
    },
    deleted_at: null,
};

const eligibleServices: ServiceOption[] = [{ id: 'svc-3', name: 'Avaliação' }];
const professionalUnits: ProfessionalUnitOption[] = [
    { id: 'unit-1', name: 'Unidade Centro' },
];

function mountAttached(props: {
    professionalId: string;
    links: ServiceLink[];
    eligibleServices: ServiceOption[];
    professionalUnits: ProfessionalUnitOption[];
}) {
    return mount(ServiceLinksSection, { props, attachTo: document.body });
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

describe('ServiceLinksSection', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('shows an empty state message when there are no service links', () => {
        const wrapper = mount(ServiceLinksSection, {
            props: {
                professionalId: 'prof-1',
                links: [],
                eligibleServices,
                professionalUnits,
            },
        });

        expect(wrapper.text()).toContain('Nenhum serviço vinculado ainda.');
    });

    it('shows the effective value marked as "(padrão)" when inherited from the service', () => {
        const wrapper = mount(ServiceLinksSection, {
            props: {
                professionalId: 'prof-1',
                links: [inheritedLink],
                eligibleServices: [],
                professionalUnits,
            },
        });

        expect(wrapper.text()).toContain('30 min (padrão)');
        expect(wrapper.text()).toContain('(padrão)');
    });

    it('shows the effective value marked as "(personalizado)" when overridden', () => {
        const wrapper = mount(ServiceLinksSection, {
            props: {
                professionalId: 'prof-1',
                links: [customLink],
                eligibleServices: [],
                professionalUnits,
            },
        });

        expect(wrapper.text()).toContain('45 min (personalizado)');
    });

    it('resolves compatible unit names from the professionalUnits list', () => {
        const wrapper = mount(ServiceLinksSection, {
            props: {
                professionalId: 'prof-1',
                links: [inheritedLink],
                eligibleServices: [],
                professionalUnits,
            },
        });

        expect(wrapper.text()).toContain('Unidade Centro');
    });

    it('warns when there is no compatible unit for an active link', () => {
        const wrapper = mount(ServiceLinksSection, {
            props: {
                professionalId: 'prof-1',
                links: [customLink],
                eligibleServices: [],
                professionalUnits,
            },
        });

        expect(wrapper.text()).toContain('nenhuma unidade compatível');
    });

    it('calls router.patch against the deactivate route for an active link', async () => {
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            links: [inheritedLink],
            eligibleServices: [],
            professionalUnits,
        });

        await openMenu(wrapper);
        await clickMenuItem('Inativar');

        expect(routerMock.patch).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/services/link-1/deactivate',
            ),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows the non-destructive confirmation wording and only calls router.delete after confirming', async () => {
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            links: [inheritedLink],
            eligibleServices: [],
            professionalUnits,
        });

        await openMenu(wrapper);
        await clickMenuItem('Remover');

        const text = document.body.textContent ?? '';
        expect(text).toContain('Remover serviço?');
        expect(text).toContain('será removido da operação');
        expect(text).toContain('histórico será preservado');
        expect(routerMock.delete).not.toHaveBeenCalled();

        const confirmButton = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) => button.textContent?.trim() === 'Remover');
        await new DOMWrapper(confirmButton as Element).trigger('click');

        expect(routerMock.delete).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/services/link-1',
            ),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows only "Restaurar" for a soft-deleted link and calls router.post on restore', async () => {
        const deleted: ServiceLink = {
            ...inheritedLink,
            deleted_at: '2026-07-19T12:00:00Z',
        };
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            links: [deleted],
            eligibleServices: [],
            professionalUnits,
        });

        await openMenu(wrapper);
        const text = document.body.textContent ?? '';
        expect(text).toContain('Restaurar');
        expect(text).not.toContain('Remover');

        await clickMenuItem('Restaurar');

        expect(routerMock.post).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/services/link-1/restore',
            ),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('opens the create sheet with the eligible services list when "Vincular serviço" is clicked', async () => {
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            links: [],
            eligibleServices,
            professionalUnits,
        });

        const newButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Vincular serviço'));
        await newButton?.trigger('click');
        await wrapper.vm.$nextTick();

        expect(document.body.textContent ?? '').toContain('Avaliação');
    });
});

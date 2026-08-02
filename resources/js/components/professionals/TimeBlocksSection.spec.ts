import { DOMWrapper, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type { TimeBlockRow } from './TimeBlocksSection.vue';
import TimeBlocksSection from './TimeBlocksSection.vue';

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

const vacation: TimeBlockRow = {
    id: 'tb-1',
    type: 'vacation',
    scope: 'all_units',
    unit: null,
    timezone: 'America/Sao_Paulo',
    starts_at: '2026-09-01T03:00:00.000000Z',
    ends_at: '2026-09-10T03:00:00.000000Z',
    is_all_day: true,
    reason: 'Férias',
    internal_notes: null,
    status: 'active',
    temporal_status: 'future',
    can_manage: true,
    deleted_at: null,
};

const absence: TimeBlockRow = {
    id: 'tb-2',
    type: 'absence',
    scope: 'specific_unit',
    unit: { id: 'unit-1', name: 'Unidade Centro' },
    timezone: 'America/Sao_Paulo',
    starts_at: '2026-09-01T12:00:00.000000Z',
    ends_at: '2026-09-01T14:00:00.000000Z',
    is_all_day: false,
    reason: null,
    internal_notes: 'Nota interna sensível',
    status: 'active',
    temporal_status: 'ongoing',
    can_manage: true,
    deleted_at: null,
};

function mountAttached(timeBlocks: TimeBlockRow[]) {
    return mount(TimeBlocksSection, {
        props: { professionalId: 'prof-1', timeBlocks, eligibleUnits: [] },
        attachTo: document.body,
    });
}

describe('TimeBlocksSection', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('shows an empty state message when there are no time blocks', () => {
        const wrapper = mount(TimeBlocksSection, {
            props: {
                professionalId: 'prof-1',
                timeBlocks: [],
                eligibleUnits: [],
            },
        });

        expect(wrapper.text()).toContain(
            'Este profissional ainda não possui ausências ou bloqueios cadastrados.',
        );
    });

    it('filters by type', async () => {
        const wrapper = mount(TimeBlocksSection, {
            props: {
                professionalId: 'prof-1',
                timeBlocks: [vacation, absence],
                eligibleUnits: [],
            },
        });

        await wrapper
            .find('select[aria-label="Filtrar bloqueios por tipo"]')
            .setValue('absence');

        expect(wrapper.find('ul').text()).toContain('Ausência');
        expect(wrapper.find('ul').text()).not.toContain('Férias');
    });

    it('filters by status', async () => {
        const wrapper = mount(TimeBlocksSection, {
            props: {
                professionalId: 'prof-1',
                timeBlocks: [vacation, { ...absence, status: 'inactive' }],
                eligibleUnits: [],
            },
        });

        await wrapper
            .find('select[aria-label="Filtrar bloqueios por status"]')
            .setValue('inactive');

        expect(wrapper.find('ul').text()).toContain('Ausência');
        expect(wrapper.find('ul').text()).not.toContain('Férias');
    });

    it('renders internal notes as plain text, never as HTML', () => {
        const wrapper = mount(TimeBlocksSection, {
            props: {
                professionalId: 'prof-1',
                timeBlocks: [{ ...absence, internal_notes: '<b>x</b>' }],
                eligibleUnits: [],
            },
        });

        expect(wrapper.html()).not.toContain('<b>x</b>');
        expect(wrapper.text()).toContain('<b>x</b>');
    });

    it('hides management actions when the row cannot be managed', () => {
        const wrapper = mount(TimeBlocksSection, {
            props: {
                professionalId: 'prof-1',
                timeBlocks: [{ ...vacation, can_manage: false }],
                eligibleUnits: [],
            },
        });

        expect(wrapper.find('button[aria-label^="Ações para"]').exists()).toBe(
            false,
        );
    });

    it('opens the create sheet when "Novo bloqueio" is clicked', async () => {
        const wrapper = mountAttached([]);

        const newButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Novo bloqueio'));
        await newButton?.trigger('click');
        await wrapper.vm.$nextTick();

        expect(document.body.textContent ?? '').toContain('Novo bloqueio');
    });

    it('calls router.patch against the deactivate route for an active block', async () => {
        const wrapper = mountAttached([vacation]);

        await wrapper.find('button[aria-label^="Ações para"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        const item = Array.from(
            document.body.querySelectorAll('[role="menuitem"]'),
        ).find((element) => element.textContent?.trim() === 'Inativar');
        await new DOMWrapper(item as Element).trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(routerMock.patch).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/time-blocks/tb-1/deactivate',
            ),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows the non-destructive confirmation wording and only calls router.delete after confirming', async () => {
        const wrapper = mountAttached([vacation]);

        await wrapper.find('button[aria-label^="Ações para"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        const item = Array.from(
            document.body.querySelectorAll('[role="menuitem"]'),
        ).find((element) => element.textContent?.trim() === 'Excluir');
        await new DOMWrapper(item as Element).trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        const text = document.body.textContent ?? '';
        expect(text).toContain('Excluir bloqueio?');
        expect(text).toContain('será removido da operação');
        expect(routerMock.delete).not.toHaveBeenCalled();

        const confirmButton = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) => button.textContent?.trim() === 'Excluir');
        await new DOMWrapper(confirmButton as Element).trigger('click');

        expect(routerMock.delete).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/time-blocks/tb-1',
            ),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows only "Restaurar" for a soft-deleted block and calls router.post on restore', async () => {
        const deleted: TimeBlockRow = {
            ...vacation,
            deleted_at: '2026-07-19T12:00:00Z',
        };
        const wrapper = mountAttached([deleted]);

        await wrapper
            .find('select[aria-label="Filtrar bloqueios por status"]')
            .setValue('deleted');

        await wrapper.find('button[aria-label^="Ações para"]').trigger('click');
        const text = document.body.textContent ?? '';
        expect(text).toContain('Restaurar');
        expect(text).not.toContain('Excluir');

        await new Promise((resolve) => setTimeout(resolve, 0));
        const item = Array.from(
            document.body.querySelectorAll('[role="menuitem"]'),
        ).find((element) => element.textContent?.trim() === 'Restaurar');
        await new DOMWrapper(item as Element).trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(routerMock.post).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/time-blocks/tb-1/restore',
            ),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});

import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type { TimeBlockRow } from '@/components/professionals/TimeBlocksSection.vue';
import TimeBlocks from './TimeBlocks.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>', props: ['href'] },
    router: { patch: vi.fn(), post: vi.fn(), delete: vi.fn() },
    useForm: (initial: Record<string, unknown>) =>
        reactive({
            ...initial,
            errors: {},
            processing: false,
            post: vi.fn(),
            put: vi.fn(),
        }),
}));

const professional = {
    id: 'prof-1',
    display_name: 'Dra. Ana Souza',
    status: 'active' as const,
};

const timeBlocks: TimeBlockRow[] = [
    {
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
    },
];

describe('settings/professionals/TimeBlocks', () => {
    it('renders the time blocks section with the provided data', () => {
        const wrapper = mount(TimeBlocks, {
            props: { professional, timeBlocks, eligibleUnits: [] },
        });

        expect(wrapper.text()).toContain('Dra. Ana Souza');
        expect(wrapper.text()).toContain('Férias');
    });

    it('highlights the "time-blocks" tab as active', () => {
        const wrapper = mount(TimeBlocks, {
            props: { professional, timeBlocks: [], eligibleUnits: [] },
        });

        const activeTab = wrapper.find('[aria-current="page"]');
        expect(activeTab.text()).toBe('Ausências e bloqueios');
    });
});

import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type { AvailabilityUnit } from '@/components/professionals/WeeklyScheduleSection.vue';
import Availability from './Availability.vue';

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

const units: AvailabilityUnit[] = [
    {
        professional_unit_id: 'pu-1',
        unit: {
            id: 'unit-1',
            name: 'Unidade Centro',
            timezone: 'America/Sao_Paulo',
        },
        unit_link_status: 'active',
        opening_hours: [
            { day_of_week: 1, opens_at: '08:00', closes_at: '18:00' },
        ],
        can_manage: true,
        working_hours: [],
    },
];

describe('settings/professionals/Availability', () => {
    it('shows an empty state when the professional has no active unit', () => {
        const wrapper = mount(Availability, {
            props: { professional, units: [] },
        });

        expect(wrapper.text()).toContain(
            'Este profissional ainda não possui unidade de atuação ativa.',
        );
    });

    it('renders one schedule section per unit', () => {
        const wrapper = mount(Availability, {
            props: { professional, units },
        });

        expect(wrapper.text()).toContain('Unidade Centro');
    });

    it('highlights the "availability" tab as active', () => {
        const wrapper = mount(Availability, {
            props: { professional, units: [] },
        });

        const activeTab = wrapper.find('[aria-current="page"]');
        expect(activeTab.text()).toBe('Jornada e disponibilidade');
    });
});

import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type {
    UnitLink,
    UnitOption,
} from '@/components/professionals/UnitLinksSection.vue';
import Units from './Units.vue';

const { routerMock } = vi.hoisted(() => ({
    routerMock: {
        patch: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>', props: ['href'] },
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

const professional = {
    id: 'prof-1',
    display_name: 'Dra. Ana Souza',
    status: 'active' as const,
};

const unitLinks: UnitLink[] = [
    {
        id: 'link-1',
        unit: { id: 'unit-1', name: 'Unidade Centro' },
        is_primary: true,
        status: 'active',
        starts_on: null,
        ends_on: null,
        vigency_status: 'in_effect',
        deleted_at: null,
    },
];

const eligibleUnits: UnitOption[] = [{ id: 'unit-2', name: 'Unidade Norte' }];

describe('settings/professionals/Units', () => {
    it('renders the unit links section with the provided data', () => {
        const wrapper = mount(Units, {
            props: { professional, unitLinks, eligibleUnits },
        });

        expect(wrapper.text()).toContain('Dra. Ana Souza');
        expect(wrapper.text()).toContain('Unidade Centro');
    });

    it('highlights the "units" tab as active', () => {
        const wrapper = mount(Units, {
            props: {
                professional,
                unitLinks: [],
                eligibleUnits: [],
            },
        });

        const activeTab = wrapper.find('[aria-current="page"]');
        expect(activeTab.text()).toBe('Unidades de atuação');
    });
});

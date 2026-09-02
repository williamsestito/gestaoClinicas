import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type {
    ProfessionalUnitOption,
    ServiceOption,
} from '@/components/professionals/ServiceAssignmentForm.vue';
import type { ServiceLink } from '@/components/professionals/ServiceLinksSection.vue';
import Services from './Services.vue';

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
        }),
}));

const professional = {
    id: 'prof-1',
    display_name: 'Dra. Ana Souza',
    status: 'active' as const,
};

const serviceLinks: ServiceLink[] = [
    {
        id: 'link-1',
        service: { id: 'svc-1', name: 'Consulta' },
        status: 'active',
        unit_scope: 'all_compatible_units',
        selected_unit_ids: [],
        compatible_units: [],
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
    },
];

const eligibleServices: ServiceOption[] = [{ id: 'svc-2', name: 'Retorno' }];
const professionalUnits: ProfessionalUnitOption[] = [];

describe('settings/professionals/Services', () => {
    it('renders the service links section with the provided data', () => {
        const wrapper = mount(Services, {
            props: {
                professional,
                serviceLinks,
                eligibleServices,
                professionalUnits,
            },
        });

        expect(wrapper.text()).toContain('Dra. Ana Souza');
        expect(wrapper.text()).toContain('Consulta');
    });

    it('highlights the "services" tab as active', () => {
        const wrapper = mount(Services, {
            props: {
                professional,
                serviceLinks: [],
                eligibleServices: [],
                professionalUnits: [],
            },
        });

        const activeTab = wrapper.find('[aria-current="page"]');
        expect(activeTab.text()).toBe('Serviços executados');
    });
});

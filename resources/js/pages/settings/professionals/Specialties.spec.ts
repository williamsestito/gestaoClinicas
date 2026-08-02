import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type { RegistrationRow } from '@/components/professionals/RegistrationsSection.vue';
import type {
    SpecialtyLink,
    SpecialtyOption,
} from '@/components/professionals/SpecialtyLinksSection.vue';
import Specialties from './Specialties.vue';

const { routerMock } = vi.hoisted(() => ({
    routerMock: {
        patch: vi.fn(),
        post: vi.fn(),
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
        }),
}));

const professional = {
    id: 'prof-1',
    display_name: 'Dra. Ana Souza',
    status: 'active' as const,
};

const specialtyLinks: SpecialtyLink[] = [
    {
        id: 'link-1',
        specialty: { id: 'sp-1', name: 'Cardiologia' },
        is_primary: true,
        status: 'active',
        deleted_at: null,
    },
];

const eligibleSpecialties: SpecialtyOption[] = [
    { id: 'sp-2', name: 'Dermatologia' },
];

const registrations: RegistrationRow[] = [
    {
        id: 'reg-1',
        council: 'CRM',
        registration_type: null,
        masked_registration_number: '••••••4321',
        state: 'SP',
        issued_at: '2020-01-01',
        expires_at: null,
        validity_status: 'no_expiration',
        is_primary: true,
        status: 'active',
        deleted_at: null,
    },
];

describe('settings/professionals/Specialties', () => {
    it('renders the specialties and registrations sections with their respective data', () => {
        const wrapper = mount(Specialties, {
            props: {
                professional,
                specialtyLinks,
                eligibleSpecialties,
                registrations,
                canViewSensitiveRegistrations: false,
            },
        });

        expect(wrapper.text()).toContain('Dra. Ana Souza');
        expect(wrapper.text()).toContain('Cardiologia');
        expect(wrapper.text()).toContain('CRM');
        expect(wrapper.text()).toContain('••••••4321');
    });

    it('highlights the "specialties" tab as active', () => {
        const wrapper = mount(Specialties, {
            props: {
                professional,
                specialtyLinks: [],
                eligibleSpecialties: [],
                registrations: [],
                canViewSensitiveRegistrations: false,
            },
        });

        const activeTab = wrapper.find('[aria-current="page"]');
        expect(activeTab.text()).toBe('Especialidades e registros');
    });
});

import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import ServiceAssignmentForm from './ServiceAssignmentForm.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (initial: Record<string, unknown>) =>
        reactive({
            ...initial,
            errors: {},
            processing: false,
            post: vi.fn(),
            put: vi.fn(),
        }),
}));

describe('ServiceAssignmentForm', () => {
    it('starts with every custom field inherited (null) and no numeric input visible', () => {
        const wrapper = mount(ServiceAssignmentForm, {
            props: {
                mode: 'create',
                professionalId: 'prof-1',
                professionalUnits: [],
                eligibleServices: [{ id: 'svc-1', name: 'Consulta' }],
            },
        });

        expect(
            wrapper
                .find('[aria-label="Duração personalizada em minutos"]')
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .find('[aria-label="Preço personalizado em reais"]')
                .exists(),
        ).toBe(false);
    });

    it('reveals the numeric input when the "usar padrão" checkbox is unchecked, and hides it again when re-checked', async () => {
        const wrapper = mount(ServiceAssignmentForm, {
            props: {
                mode: 'create',
                professionalId: 'prof-1',
                professionalUnits: [],
                eligibleServices: [],
            },
        });

        const checkbox = wrapper
            .findAll('input[type="checkbox"]')
            .at(0) as ReturnType<typeof wrapper.find>;
        await checkbox.setValue(false);

        const durationInput = wrapper.find(
            '[aria-label="Duração personalizada em minutos"]',
        );
        expect(durationInput.exists()).toBe(true);

        await checkbox.setValue(true);
        expect(
            wrapper
                .find('[aria-label="Duração personalizada em minutos"]')
                .exists(),
        ).toBe(false);
    });

    it('only shows the service select in create mode', () => {
        const createWrapper = mount(ServiceAssignmentForm, {
            props: {
                mode: 'create',
                professionalId: 'prof-1',
                professionalUnits: [],
                eligibleServices: [],
            },
        });
        expect(createWrapper.find('#service-assignment-service').exists()).toBe(
            true,
        );

        const editWrapper = mount(ServiceAssignmentForm, {
            props: {
                mode: 'edit',
                professionalId: 'prof-1',
                professionalUnits: [],
                link: {
                    id: 'link-1',
                    custom_duration_minutes: null,
                    custom_price: null,
                    custom_buffer_before_minutes: null,
                    custom_buffer_after_minutes: null,
                    unit_scope: 'all_compatible_units',
                    unit_ids: [],
                    defaults: {
                        duration_minutes: 30,
                        price: null,
                        buffer_before_minutes: 0,
                        buffer_after_minutes: 0,
                    },
                },
            },
        });
        expect(editWrapper.find('#service-assignment-service').exists()).toBe(
            false,
        );
    });

    it('disables the submit button in create mode until a service is selected', async () => {
        const wrapper = mount(ServiceAssignmentForm, {
            props: {
                mode: 'create',
                professionalId: 'prof-1',
                professionalUnits: [],
                eligibleServices: [{ id: 'svc-1', name: 'Consulta' }],
            },
        });

        const submitButton = wrapper
            .findAll('button')
            .find((button) => button.attributes('type') === 'submit');
        expect(submitButton?.attributes('disabled')).toBeDefined();

        await wrapper.find('#service-assignment-service').setValue('svc-1');
        expect(submitButton?.attributes('disabled')).toBeUndefined();
    });

    it('only shows the unit checklist when unit_scope is "selected_units"', async () => {
        const wrapper = mount(ServiceAssignmentForm, {
            props: {
                mode: 'create',
                professionalId: 'prof-1',
                professionalUnits: [{ id: 'unit-1', name: 'Unidade Centro' }],
                eligibleServices: [],
            },
        });

        expect(wrapper.text()).not.toContain('Unidade Centro');

        const radios = wrapper.findAll('input[type="radio"]');
        await radios[1].setValue(true);

        expect(wrapper.text()).toContain('Unidade Centro');
    });
});

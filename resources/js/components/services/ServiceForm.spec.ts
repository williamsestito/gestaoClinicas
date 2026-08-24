import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ServiceForm from './ServiceForm.vue';

const { postMock } = vi.hoisted(() => ({ postMock: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    useForm: (initial: Record<string, unknown>) => {
        const form = {
            ...initial,
            errors: {},
            processing: false,
            post: postMock,
            put: vi.fn(),
        };

        return form;
    },
}));

describe('components/services/ServiceForm — Etapa 5 pricing fields', () => {
    it('submits the new cost, margin and max-discount fields alongside the rest of the form', async () => {
        const wrapper = mount(ServiceForm, {
            props: { mode: 'create', specialties: [], units: [] },
        });

        await wrapper.find('#service-name').setValue('Massagem');
        await wrapper.find('#service-code').setValue('MAS-01');
        await wrapper.find('#service-cost').setValue(20);
        await wrapper.find('#service-margin').setValue(50);
        await wrapper.find('#service-price').setValue(30);
        await wrapper.find('#service-max-discount').setValue(10);
        await wrapper.find('form').trigger('submit');

        expect(postMock).toHaveBeenCalled();
    });

    it('pre-fills the pricing fields when editing an existing service', () => {
        const wrapper = mount(ServiceForm, {
            props: {
                mode: 'edit',
                specialties: [],
                units: [],
                service: {
                    id: 'service-1',
                    name: 'Massagem',
                    code: 'MAS-01',
                    description: null,
                    default_duration_minutes: 30,
                    buffer_before_minutes: 0,
                    buffer_after_minutes: 0,
                    default_price: 30,
                    cost: 20,
                    margin_percentage: 50,
                    max_discount_percentage: 10,
                    color: null,
                    is_public: false,
                    requires_manual_confirmation: false,
                    internal_notes: null,
                    unit_availability_scope: 'all_units',
                    specialty_ids: [],
                    unit_ids: [],
                },
            },
        });

        expect(
            (wrapper.find('#service-cost').element as HTMLInputElement).value,
        ).toBe('20');
        expect(
            (wrapper.find('#service-margin').element as HTMLInputElement).value,
        ).toBe('50');
        expect(
            (wrapper.find('#service-max-discount').element as HTMLInputElement)
                .value,
        ).toBe('10');
    });
});

import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import WorkingHourForm from './WorkingHourForm.vue';

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

describe('WorkingHourForm', () => {
    it('defaults the weekday select to the provided defaultWeekday', () => {
        const wrapper = mount(WorkingHourForm, {
            props: {
                mode: 'create',
                professionalId: 'prof-1',
                professionalUnitId: 'pu-1',
                defaultWeekday: 3,
            },
        });

        expect(
            (wrapper.find('#working-hour-weekday').element as HTMLSelectElement)
                .value,
        ).toBe('3');
    });

    it('pre-fills the interval fields in edit mode', () => {
        const wrapper = mount(WorkingHourForm, {
            props: {
                mode: 'edit',
                professionalId: 'prof-1',
                professionalUnitId: 'pu-1',
                workingHour: {
                    id: 'wh-1',
                    weekday: 2,
                    starts_at: '08:00',
                    ends_at: '12:00',
                    effective_from: null,
                    effective_until: null,
                },
            },
        });

        expect(
            (wrapper.find('#working-hour-starts').element as HTMLInputElement)
                .value,
        ).toBe('08:00');
        expect(
            (wrapper.find('#working-hour-ends').element as HTMLInputElement)
                .value,
        ).toBe('12:00');
    });

    it('shows the correct submit label for create and edit modes', () => {
        const createWrapper = mount(WorkingHourForm, {
            props: {
                mode: 'create',
                professionalId: 'prof-1',
                professionalUnitId: 'pu-1',
            },
        });
        expect(createWrapper.text()).toContain('Adicionar intervalo');

        const editWrapper = mount(WorkingHourForm, {
            props: {
                mode: 'edit',
                professionalId: 'prof-1',
                professionalUnitId: 'pu-1',
                workingHour: {
                    id: 'wh-1',
                    weekday: 1,
                    starts_at: '08:00',
                    ends_at: '12:00',
                    effective_from: null,
                    effective_until: null,
                },
            },
        });
        expect(editWrapper.text()).toContain('Salvar alterações');
    });

    it('emits cancel when the cancel button is clicked', async () => {
        const wrapper = mount(WorkingHourForm, {
            props: {
                mode: 'create',
                professionalId: 'prof-1',
                professionalUnitId: 'pu-1',
            },
        });

        const cancelButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Cancelar');
        await cancelButton?.trigger('click');

        expect(wrapper.emitted('cancel')).toBeTruthy();
    });
});

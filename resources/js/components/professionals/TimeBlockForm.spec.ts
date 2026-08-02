import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import TimeBlockForm from './TimeBlockForm.vue';

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

describe('TimeBlockForm', () => {
    it('defaults to the "all units" scope and hides the unit select', () => {
        const wrapper = mount(TimeBlockForm, {
            props: {
                mode: 'create',
                professionalId: 'prof-1',
                eligibleUnits: [],
            },
        });

        expect(wrapper.find('#time-block-unit').exists()).toBe(false);
    });

    it('shows the unit select only when the scope is set to a specific unit', async () => {
        const wrapper = mount(TimeBlockForm, {
            props: {
                mode: 'create',
                professionalId: 'prof-1',
                eligibleUnits: [{ id: 'unit-1', name: 'Unidade Centro' }],
            },
        });

        const specificUnitRadio = wrapper
            .findAll('input[type="radio"]')
            .find(
                (input) =>
                    (input.element as HTMLInputElement).value ===
                    'specific_unit',
            );
        await specificUnitRadio?.setValue();

        expect(wrapper.find('#time-block-unit').exists()).toBe(true);
        expect(wrapper.text()).toContain('Unidade Centro');
    });

    it('hides the time inputs when "dia inteiro" is checked', async () => {
        const wrapper = mount(TimeBlockForm, {
            props: {
                mode: 'create',
                professionalId: 'prof-1',
                eligibleUnits: [],
            },
        });

        expect(wrapper.find('#time-block-starts-time').exists()).toBe(false);

        const allDayCheckbox = wrapper.find('input[type="checkbox"]');
        await allDayCheckbox.setValue(false);

        expect(wrapper.find('#time-block-starts-time').exists()).toBe(true);
        expect(wrapper.find('#time-block-ends-time').exists()).toBe(true);
    });

    it('pre-fills the reason and internal notes in edit mode', () => {
        const wrapper = mount(TimeBlockForm, {
            props: {
                mode: 'edit',
                professionalId: 'prof-1',
                eligibleUnits: [],
                timeBlock: {
                    id: 'tb-1',
                    type: 'vacation',
                    scope: 'all_units',
                    unit: null,
                    timezone: 'America/Sao_Paulo',
                    starts_at: '2026-09-01T03:00:00.000000Z',
                    ends_at: '2026-09-10T03:00:00.000000Z',
                    is_all_day: true,
                    reason: 'Férias de fim de ano',
                    internal_notes: 'Confirmado com RH',
                },
            },
        });

        expect(
            (wrapper.find('#time-block-reason').element as HTMLInputElement)
                .value,
        ).toBe('Férias de fim de ano');
        expect(
            (
                wrapper.find('#time-block-internal-notes')
                    .element as HTMLTextAreaElement
            ).value,
        ).toBe('Confirmado com RH');
    });

    it('emits cancel when the cancel button is clicked', async () => {
        const wrapper = mount(TimeBlockForm, {
            props: {
                mode: 'create',
                professionalId: 'prof-1',
                eligibleUnits: [],
            },
        });

        const cancelButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Cancelar');
        await cancelButton?.trigger('click');

        expect(wrapper.emitted('cancel')).toBeTruthy();
    });
});

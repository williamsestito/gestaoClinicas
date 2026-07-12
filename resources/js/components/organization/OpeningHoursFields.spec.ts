import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import OpeningHoursFields from './OpeningHoursFields.vue';

describe('OpeningHoursFields', () => {
    it('shows an empty state when there are no hours yet', () => {
        const wrapper = mount(OpeningHoursFields, {
            props: { modelValue: [] },
        });

        expect(wrapper.text()).toContain(
            'Nenhum horário de funcionamento cadastrado ainda.',
        );
    });

    it('adds a new row when "Adicionar horário" is clicked', async () => {
        const wrapper = mount(OpeningHoursFields, {
            props: { modelValue: [] },
        });

        await wrapper.find('button').trigger('click');

        const emitted = wrapper.emitted('update:modelValue');
        expect(emitted).toBeTruthy();
        expect(emitted![0][0]).toHaveLength(1);
    });

    it('removes a row when "Remover" is clicked', async () => {
        const wrapper = mount(OpeningHoursFields, {
            props: {
                modelValue: [
                    { day_of_week: 1, opens_at: '08:00', closes_at: '18:00' },
                ],
            },
        });

        const removeButton = wrapper
            .findAll('button')
            .find((btn) => btn.text() === 'Remover');
        await removeButton?.trigger('click');

        const emitted = wrapper.emitted('update:modelValue');
        expect(emitted![0][0]).toHaveLength(0);
    });

    it('shows the validation error message when provided', () => {
        const wrapper = mount(OpeningHoursFields, {
            props: { modelValue: [], error: 'Os horários se sobrepõem.' },
        });

        expect(wrapper.text()).toContain('Os horários se sobrepõem.');
    });
});

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SimpleBarChart from './SimpleBarChart.vue';

describe('SimpleBarChart', () => {
    it('shows the empty message when there is no data', () => {
        const wrapper = mount(SimpleBarChart, {
            props: { title: 'Agendamentos', data: [] },
        });

        expect(wrapper.text()).toContain('Sem dados neste período.');
    });

    it('renders one bar per data point, with the label and formatted value', () => {
        const wrapper = mount(SimpleBarChart, {
            props: {
                title: 'Agendamentos por dia da semana',
                data: [
                    { label: 'Seg', value: 5 },
                    { label: 'Ter', value: 2 },
                ],
            },
        });

        const items = wrapper.findAll('li');
        expect(items).toHaveLength(2);
        expect(wrapper.text()).toContain('Seg');
        expect(wrapper.text()).toContain('Ter');
        expect(wrapper.text()).toContain('5');
        expect(wrapper.text()).toContain('2');
    });

    it('sizes the largest bar at 100% width, relative to the others', () => {
        const wrapper = mount(SimpleBarChart, {
            props: {
                title: 'Ocupação',
                data: [
                    { label: 'A', value: 10 },
                    { label: 'B', value: 5 },
                ],
            },
        });

        const bars = wrapper.findAll('li span[style]');
        expect(bars[0].attributes('style')).toContain('width: 100%');
        expect(bars[1].attributes('style')).toContain('width: 50%');
    });

    it('applies a custom value formatter when provided', () => {
        const wrapper = mount(SimpleBarChart, {
            props: {
                title: 'Faturamento',
                data: [{ label: 'Jan/26', value: 150000 }],
                formatValue: (value) => `R$ ${(value / 100).toFixed(2)}`,
            },
        });

        expect(wrapper.text()).toContain('R$ 1500.00');
    });
});

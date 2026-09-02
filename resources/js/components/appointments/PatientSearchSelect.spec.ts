import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PatientSearchSelect from './PatientSearchSelect.vue';

async function typeQuery(
    wrapper: ReturnType<typeof mount>,
    value: string,
): Promise<void> {
    await wrapper.find('input').setValue(value);
    await vi.advanceTimersByTimeAsync(300);
}

describe('PatientSearchSelect', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });

    it('lists patients returned by the search endpoint and selects one on click', async () => {
        vi.useFakeTimers();
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => ({
                    patients: [
                        {
                            id: 'p1',
                            name: 'Maria da Silva',
                            birth_date: '1990-01-01',
                        },
                    ],
                }),
            }),
        );

        const wrapper = mount(PatientSearchSelect, {
            props: { modelValue: '' },
        });

        await typeQuery(wrapper, 'Maria');

        expect(wrapper.text()).toContain('Maria da Silva');

        await wrapper.find('li button').trigger('click');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['p1']);
        expect(wrapper.text()).toContain('Maria da Silva');
        expect(wrapper.find('input').exists()).toBe(false);
    });

    it('shows an empty-state message when the search finds no patient', async () => {
        vi.useFakeTimers();
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => ({ patients: [] }),
            }),
        );

        const wrapper = mount(PatientSearchSelect, {
            props: { modelValue: '' },
        });

        await typeQuery(wrapper, 'testeuchoa');

        expect(wrapper.text()).toContain('Nenhum paciente encontrado');
        expect(wrapper.find('li').exists()).toBe(false);
    });

    it('shows an error message instead of a silent empty list when the request fails (e.g. 403)', async () => {
        vi.useFakeTimers();
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({ ok: false, status: 403 }),
        );

        const wrapper = mount(PatientSearchSelect, {
            props: { modelValue: '' },
        });

        await typeQuery(wrapper, 'Maria');

        expect(wrapper.text()).toContain('Não foi possível buscar pacientes');
        expect(wrapper.find('li').exists()).toBe(false);
    });

    it('never searches for a query shorter than 2 characters', async () => {
        vi.useFakeTimers();
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(PatientSearchSelect, {
            props: { modelValue: '' },
        });

        await typeQuery(wrapper, 'M');

        expect(fetchMock).not.toHaveBeenCalled();
        expect(wrapper.text()).not.toContain('Nenhum paciente encontrado');
    });
});

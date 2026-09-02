import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PhoneInput from './PhoneInput.vue';

describe('PhoneInput', () => {
    it('masks raw digits into (DD) DDDDD-DDDD as the value updates', async () => {
        const wrapper = mount(PhoneInput, {
            props: { modelValue: '' },
        });

        await wrapper.find('input').setValue('47996961511');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
            '(47) 99696-1511',
        ]);
    });

    it('ignores non-digit characters typed by the person', async () => {
        const wrapper = mount(PhoneInput, {
            props: { modelValue: '' },
        });

        await wrapper.find('input').setValue('(47) 99696-1511 abc');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
            '(47) 99696-1511',
        ]);
    });

    it('caps the value at 11 digits — a mobile phone, never more', async () => {
        const wrapper = mount(PhoneInput, {
            props: { modelValue: '' },
        });

        await wrapper.find('input').setValue('479969615119999');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
            '(47) 99696-1511',
        ]);
    });

    it('formats a 10-digit landline while the person is still typing toward 11', async () => {
        const wrapper = mount(PhoneInput, {
            props: { modelValue: '' },
        });

        await wrapper.find('input').setValue('4732221100');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
            '(47) 3222-1100',
        ]);
    });

    it('displays the initial masked value from the model', () => {
        const wrapper = mount(PhoneInput, {
            props: { modelValue: '(47) 99696-1511' },
        });

        expect((wrapper.find('input').element as HTMLInputElement).value).toBe(
            '(47) 99696-1511',
        );
    });
});

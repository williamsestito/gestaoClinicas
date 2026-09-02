import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import WorkingHourWizard from './WorkingHourWizard.vue';

const postMock = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    useForm: (initial: Record<string, unknown>) =>
        reactive({
            ...initial,
            errors: {},
            processing: false,
            post: postMock,
        }),
}));

function mountWizard() {
    return mount(WorkingHourWizard, {
        props: {
            professionalId: 'prof-1',
            professionalUnitId: 'pu-1',
        },
    });
}

async function fillVigencyAndAdvance(
    wrapper: ReturnType<typeof mountWizard>,
    from = '2026-08-01',
    until = '2026-08-31',
) {
    await wrapper.find('#wizard-effective-from').setValue(from);
    await wrapper.find('#wizard-effective-until').setValue(until);
    await wrapper
        .findAll('button')
        .find((button) => button.text() === 'Próximo')
        ?.trigger('click');
}

describe('WorkingHourWizard', () => {
    beforeEach(() => {
        postMock.mockClear();
    });

    it('starts on the "Vigência" step with the "Próximo" button disabled until both dates are filled', () => {
        const wrapper = mountWizard();

        expect(wrapper.text()).toContain('Vigência inicial');
        const nextButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Próximo');
        expect(nextButton?.attributes('disabled')).toBeDefined();
    });

    it('enables "Próximo" only once both vigency dates are filled and in order', async () => {
        const wrapper = mountWizard();

        await wrapper.find('#wizard-effective-from').setValue('2026-08-31');
        await wrapper.find('#wizard-effective-until').setValue('2026-08-01');
        let nextButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Próximo');
        expect(nextButton?.attributes('disabled')).toBeDefined();

        await wrapper.find('#wizard-effective-until').setValue('2026-08-31');
        nextButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Próximo');
        expect(nextButton?.attributes('disabled')).toBeUndefined();
    });

    it('pre-selects Monday through Friday as a suggestion, with Saturday and Sunday left unchecked', async () => {
        const wrapper = mountWizard();
        await fillVigencyAndAdvance(wrapper);

        const checkboxes = wrapper.findAll('input[type="checkbox"]');
        const checkedLabels = wrapper
            .findAll('label')
            .filter((label) =>
                label.find('input[type="checkbox"]:checked').exists(),
            )
            .map((label) => label.text());

        expect(checkedLabels).toEqual([
            'Segunda-feira',
            'Terça-feira',
            'Quarta-feira',
            'Quinta-feira',
            'Sexta-feira',
        ]);
        expect(checkboxes).toHaveLength(7);
    });

    it('lets the professional explicitly include Saturday/Sunday, never auto-toggled', async () => {
        const wrapper = mountWizard();
        await fillVigencyAndAdvance(wrapper);

        const saturdayCheckbox = wrapper
            .findAll('label')
            .find((label) => label.text() === 'Sábado')
            ?.find('input[type="checkbox"]');

        expect((saturdayCheckbox?.element as HTMLInputElement).checked).toBe(
            false,
        );

        await saturdayCheckbox?.setValue(true);

        const checkedLabels = wrapper
            .findAll('label')
            .filter((label) =>
                label.find('input[type="checkbox"]:checked').exists(),
            )
            .map((label) => label.text());
        expect(checkedLabels).toContain('Sábado');
    });

    it('blocks advancing past the weekday step when every day is unchecked', async () => {
        const wrapper = mountWizard();
        await fillVigencyAndAdvance(wrapper);

        for (const label of wrapper.findAll('label')) {
            const checkbox = label.find('input[type="checkbox"]');

            if ((checkbox.element as HTMLInputElement).checked) {
                await checkbox.setValue(false);
            }
        }

        const nextButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Próximo');
        expect(nextButton?.attributes('disabled')).toBeDefined();
    });

    it('starts with a single interval row and supports adding/removing rows, never going below one', async () => {
        const wrapper = mountWizard();
        await fillVigencyAndAdvance(wrapper);
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Próximo')
            ?.trigger('click');

        expect(wrapper.findAll('#wizard-interval-start-0')).toHaveLength(1);
        expect(wrapper.find('#wizard-interval-start-1').exists()).toBe(false);

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Adicionar intervalo')
            ?.trigger('click');
        expect(wrapper.find('#wizard-interval-start-1').exists()).toBe(true);

        const removeButtons = () =>
            wrapper
                .findAll('button')
                .filter((button) => button.text() === 'Remover');
        expect(removeButtons()).toHaveLength(2);

        await removeButtons()[1].trigger('click');
        expect(wrapper.find('#wizard-interval-start-1').exists()).toBe(false);
        expect(removeButtons()[0].attributes('disabled')).toBeDefined();
    });

    it('blocks advancing past the intervals step until every row has a valid start < end', async () => {
        const wrapper = mountWizard();
        await fillVigencyAndAdvance(wrapper);
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Próximo')
            ?.trigger('click');

        let nextButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Próximo');
        expect(nextButton?.attributes('disabled')).toBeDefined();

        await wrapper.find('#wizard-interval-start-0').setValue('12:00');
        await wrapper.find('#wizard-interval-end-0').setValue('08:00');
        nextButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Próximo');
        expect(nextButton?.attributes('disabled')).toBeDefined();

        await wrapper.find('#wizard-interval-end-0').setValue('18:00');
        nextButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Próximo');
        expect(nextButton?.attributes('disabled')).toBeUndefined();
    });

    it('computes the summary estimate by counting only the selected weekdays inside the vigency, as a client-side preview', async () => {
        const wrapper = mountWizard();
        // Agosto de 2026: dia 1 é sábado. Só seg-sex (padrão) até 07/08 —
        // 03, 04, 05, 06, 07 são as 5 segundas-sextas dessa primeira semana.
        await fillVigencyAndAdvance(wrapper, '2026-08-01', '2026-08-07');
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Próximo')
            ?.trigger('click');
        await wrapper.find('#wizard-interval-start-0').setValue('08:00');
        await wrapper.find('#wizard-interval-end-0').setValue('12:00');
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Próximo')
            ?.trigger('click');

        expect(wrapper.text()).toContain('Estimativa de dias com atendimento:');
        expect(wrapper.text()).toContain('5');
    });

    it('submits to the batch configure endpoint with the professional and unit ids from props', async () => {
        const wrapper = mountWizard();
        await fillVigencyAndAdvance(wrapper);
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Próximo')
            ?.trigger('click');
        await wrapper.find('#wizard-interval-start-0').setValue('08:00');
        await wrapper.find('#wizard-interval-end-0').setValue('12:00');
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Próximo')
            ?.trigger('click');

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Confirmar e salvar')
            ?.trigger('click');

        expect(postMock).toHaveBeenCalledTimes(1);
        const [url] = postMock.mock.calls[0];
        expect(url).toContain('prof-1');
        expect(url).toContain('pu-1');
        expect(url).toContain('/working-hours/configure');
    });

    it('emits cancel from the first step without submitting anything', async () => {
        const wrapper = mountWizard();

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Cancelar')
            ?.trigger('click');

        expect(wrapper.emitted('cancel')).toBeTruthy();
        expect(postMock).not.toHaveBeenCalled();
    });

    it('goes back a step instead of cancelling once past the first step', async () => {
        const wrapper = mountWizard();
        await fillVigencyAndAdvance(wrapper);

        expect(wrapper.text()).toContain('Segunda a sexta já vem marcado');

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Voltar')
            ?.trigger('click');

        expect(wrapper.text()).toContain('Vigência inicial');
        expect(wrapper.emitted('cancel')).toBeFalsy();
    });
});

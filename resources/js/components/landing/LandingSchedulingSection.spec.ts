import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, reactive } from 'vue';
import { useLandingScheduling } from '@/composables/useLandingScheduling';
import LandingSchedulingSection from './LandingSchedulingSection.vue';

const { formState, postMock } = vi.hoisted(() => ({
    formState: {
        service_id: null as number | null,
        name: '',
        phone: '',
        email: '',
        preferred_period: '',
        preferred_date: '',
        notes: '',
        terms_accepted: false,
        website: '',
        form_rendered_at: 0,
        utm: {} as Record<string, string>,
        errors: {} as Record<string, string>,
        processing: false,
        recentlySuccessful: false,
        post: vi.fn(),
        reset: vi.fn(),
    },
    postMock: vi.fn(),
}));

formState.post = postMock;

vi.mock('@inertiajs/vue3', () => ({
    // Aplica os valores iniciais reais do componente (inclusive os
    // calculados no momento da montagem, como `utm`/`form_rendered_at`)
    // sobre o MESMO objeto reativo compartilhado entre chamadas — as
    // mutações feitas pelo componente (ex.: pré-seleção de serviço)
    // precisam ficar visíveis para as asserções do teste, que leem
    // `formState` diretamente.
    useForm: (initial: Record<string, unknown> = {}) => {
        Object.assign(formState, initial);

        return reactive(formState);
    },
}));

beforeEach(() => {
    postMock.mockReset();
    formState.processing = false;
    formState.recentlySuccessful = false;
    formState.service_id = null;
    formState.name = '';
    formState.phone = '';
    formState.terms_accepted = false;
    formState.notes = '';
    formState.preferred_date = '';
    formState.preferred_period = '';
    formState.errors = {};
    useLandingScheduling().selectedServiceId.value = null;
    useLandingScheduling().selectedProfessionalName.value = null;
    useLandingScheduling().preferredDate.value = null;
    useLandingScheduling().preferredPeriod.value = null;
});

describe('LandingSchedulingSection', () => {
    it('renders the required fields for a lead: name and phone', () => {
        const wrapper = mount(LandingSchedulingSection);

        expect(wrapper.find('#name').exists()).toBe(true);
        expect(wrapper.find('#phone').exists()).toBe(true);
        expect(wrapper.find('#name').attributes('required')).toBeDefined();
        expect(wrapper.find('#phone').attributes('required')).toBeDefined();
    });

    it('masks the phone field as the person types, with no manual formatting required', async () => {
        const wrapper = mount(LandingSchedulingSection);

        const phoneInput = wrapper.find('#phone');
        await phoneInput.setValue('47996961511');

        expect((phoneInput.element as HTMLInputElement).value).toBe(
            '(47) 99696-1511',
        );
    });

    it('no longer shows a manual "Serviço de interesse" picker — service is only pre-filled via the shared composable', () => {
        const wrapper = mount(LandingSchedulingSection);

        expect(wrapper.text()).not.toContain('Serviço de interesse');
        expect(wrapper.find('#service_id').exists()).toBe(false);
    });

    it('disables the submit button while the request is processing, preventing duplicate submissions', () => {
        formState.processing = true;
        const wrapper = mount(LandingSchedulingSection);

        const button = wrapper.find('button[type="submit"]');
        expect(button.attributes('disabled')).toBeDefined();
    });

    it('shows a success message and hides the form once the submission succeeds', () => {
        formState.recentlySuccessful = true;
        const wrapper = mount(LandingSchedulingSection);

        expect(wrapper.text()).toContain('Pré-agendamento enviado!');
        expect(wrapper.text()).toContain('encaminhada à clínica');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('labels the submit button "Criar pré-agendamento" and only validates/submits on that click', () => {
        const wrapper = mount(LandingSchedulingSection);

        expect(wrapper.find('button[type="submit"]').text()).toContain(
            'Criar pré-agendamento',
        );
        expect(postMock).not.toHaveBeenCalled();
    });

    it('pre-fills the hidden service_id from the shared composable (set by "Agendar" elsewhere on the page)', () => {
        useLandingScheduling().selectedServiceId.value = 1;

        mount(LandingSchedulingSection);

        expect(formState.service_id).toBe(1);
    });

    it('renders an optional preferred date field bounded to a sensible window', () => {
        const wrapper = mount(LandingSchedulingSection);

        const dateInput = wrapper.find('#preferred_date');
        expect(dateInput.exists()).toBe(true);
        expect(dateInput.attributes('type')).toBe('date');
        expect(dateInput.attributes('min')).toBeTruthy();
        expect(dateInput.attributes('max')).toBeTruthy();
    });

    it('keeps the honeypot field unreachable by keyboard and hidden from screen readers', () => {
        const wrapper = mount(LandingSchedulingSection);

        const honeypot = wrapper.find('#website');
        expect(honeypot.exists()).toBe(true);
        expect(honeypot.attributes('tabindex')).toBe('-1');
        expect(honeypot.element.closest('[aria-hidden="true"]')).not.toBeNull();
    });

    it('clarifies that submitting the form does not guarantee a reservation', () => {
        const wrapper = mount(LandingSchedulingSection);

        expect(wrapper.text()).toContain('não garante reserva');
    });

    it('shows the four-step explainer of how the request flow works', () => {
        const wrapper = mount(LandingSchedulingSection);

        expect(wrapper.findAll('ol > li')).toHaveLength(4);
        expect(wrapper.text()).toContain('Escolha o serviço');
        expect(wrapper.text()).toContain('Aguarde a confirmação');
    });

    it('prefills the notes with the professional chosen on the team section, without overwriting existing notes', () => {
        useLandingScheduling().selectedProfessionalName.value = 'Dra. Ana';

        mount(LandingSchedulingSection);

        expect(formState.notes).toBe('Gostaria de agendar com Dra. Ana.');
    });

    it('updates the notes again when a different time slot is chosen, but stops once the person edits them by hand', async () => {
        const scheduling = useLandingScheduling();
        scheduling.selectedProfessionalName.value =
            'Dra. Ana — Consulta, 09:00';

        mount(LandingSchedulingSection);
        expect(formState.notes).toBe(
            'Gostaria de agendar com Dra. Ana — Consulta, 09:00.',
        );

        // Escolher outro horário deve atualizar o texto gerado automaticamente.
        scheduling.selectedProfessionalName.value =
            'Dra. Ana — Consulta, 10:00';
        await nextTick();
        expect(formState.notes).toBe(
            'Gostaria de agendar com Dra. Ana — Consulta, 10:00.',
        );

        // Depois que a pessoa edita manualmente, uma nova escolha de horário
        // não sobrescreve o que ela escreveu.
        formState.notes = 'Prefiro de manhã, se possível.';
        scheduling.selectedProfessionalName.value =
            'Dra. Ana — Consulta, 11:00';
        await nextTick();
        expect(formState.notes).toBe('Prefiro de manhã, se possível.');
    });

    it('captures utm parameters and the referrer when the form is submitted', () => {
        const originalLocation = window.location;
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: {
                ...originalLocation,
                search: '?utm_source=google&utm_medium=cpc',
                href: 'https://example.test/?utm_source=google&utm_medium=cpc',
            },
        });

        mount(LandingSchedulingSection);

        expect(formState.utm).toMatchObject({
            utm_source: 'google',
            utm_medium: 'cpc',
        });

        Object.defineProperty(window, 'location', {
            configurable: true,
            value: originalLocation,
        });
    });

    it('pre-fills the preferred date and period chosen on the availability search, without submitting anything', () => {
        const scheduling = useLandingScheduling();
        scheduling.preferredDate.value = '2026-08-10';
        scheduling.preferredPeriod.value = 'Manhã';

        mount(LandingSchedulingSection);

        expect(formState.preferred_date).toBe('2026-08-10');
        expect(formState.preferred_period).toBe('Manhã');
        expect(postMock).not.toHaveBeenCalled();
    });

    it('updates the preferred date/period again when a different time slot is chosen', async () => {
        const scheduling = useLandingScheduling();
        scheduling.preferredDate.value = '2026-08-10';
        scheduling.preferredPeriod.value = 'Manhã';

        mount(LandingSchedulingSection);
        expect(formState.preferred_period).toBe('Manhã');

        scheduling.preferredDate.value = '2026-08-11';
        scheduling.preferredPeriod.value = 'Tarde';
        await nextTick();

        expect(formState.preferred_date).toBe('2026-08-11');
        expect(formState.preferred_period).toBe('Tarde');
    });

    it('never validates or submits just from choosing a service, professional or time slot — only the submit button does', async () => {
        const scheduling = useLandingScheduling();
        scheduling.selectedServiceId.value = 7;
        scheduling.selectedProfessionalName.value =
            'Dra. Ana Souza — Consulta, 09:00';
        scheduling.preferredDate.value = '2026-08-10';
        scheduling.preferredPeriod.value = 'Manhã';

        mount(LandingSchedulingSection);
        await nextTick();

        expect(postMock).not.toHaveBeenCalled();
        expect(formState.errors).toEqual({});
    });
});

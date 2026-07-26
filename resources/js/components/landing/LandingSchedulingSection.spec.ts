import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { useLandingScheduling } from '@/composables/useLandingScheduling';
import type { PublicService } from '@/types/site';
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

const services: PublicService[] = [
    {
        id: 1,
        name: 'Limpeza de pele',
        short_description: null,
        description: null,
        image_url: null,
        icon: null,
        category: null,
        duration_minutes: 60,
        starting_price_cents: 12000,
        cta_text: null,
        is_featured: false,
    },
];

beforeEach(() => {
    postMock.mockReset();
    formState.processing = false;
    formState.recentlySuccessful = false;
    formState.service_id = null;
    useLandingScheduling().selectedServiceId.value = null;
});

describe('LandingSchedulingSection', () => {
    it('renders the required fields for a lead: name and phone', () => {
        const wrapper = mount(LandingSchedulingSection, {
            props: { services: [] },
        });

        expect(wrapper.find('#name').exists()).toBe(true);
        expect(wrapper.find('#phone').exists()).toBe(true);
        expect(wrapper.find('#name').attributes('required')).toBeDefined();
        expect(wrapper.find('#phone').attributes('required')).toBeDefined();
    });

    it('disables the submit button while the request is processing, preventing duplicate submissions', async () => {
        formState.processing = true;
        const wrapper = mount(LandingSchedulingSection, {
            props: { services: [] },
        });

        const button = wrapper.find('button[type="submit"]');
        expect(button.attributes('disabled')).toBeDefined();
    });

    it('shows a success message and hides the form once the submission succeeds', () => {
        formState.recentlySuccessful = true;
        const wrapper = mount(LandingSchedulingSection, {
            props: { services: [] },
        });

        expect(wrapper.text()).toContain('Solicitação enviada!');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('pre-selects the service chosen on the services section via the shared composable', () => {
        useLandingScheduling().selectedServiceId.value = 1;

        mount(LandingSchedulingSection, { props: { services } });

        expect(formState.service_id).toBe(1);
    });

    it('renders an optional preferred date field bounded to a sensible window', () => {
        const wrapper = mount(LandingSchedulingSection, {
            props: { services: [] },
        });

        const dateInput = wrapper.find('#preferred_date');
        expect(dateInput.exists()).toBe(true);
        expect(dateInput.attributes('type')).toBe('date');
        expect(dateInput.attributes('min')).toBeTruthy();
        expect(dateInput.attributes('max')).toBeTruthy();
    });

    it('keeps the honeypot field unreachable by keyboard and hidden from screen readers', () => {
        const wrapper = mount(LandingSchedulingSection, {
            props: { services: [] },
        });

        const honeypot = wrapper.find('#website');
        expect(honeypot.exists()).toBe(true);
        expect(honeypot.attributes('tabindex')).toBe('-1');
        expect(
            honeypot.element.closest('[aria-hidden="true"]'),
        ).not.toBeNull();
    });

    it('clarifies that submitting the form does not guarantee a reservation', () => {
        const wrapper = mount(LandingSchedulingSection, {
            props: { services: [] },
        });

        expect(wrapper.text()).toContain('não garante reserva');
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

        mount(LandingSchedulingSection, { props: { services: [] } });

        expect(formState.utm).toMatchObject({
            utm_source: 'google',
            utm_medium: 'cpc',
        });

        Object.defineProperty(window, 'location', {
            configurable: true,
            value: originalLocation,
        });
    });
});

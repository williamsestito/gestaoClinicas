import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Index from './Index.vue';

function findButton(text: string): HTMLElement {
    const button = Array.from(document.body.querySelectorAll('button')).find(
        (candidate) => candidate.textContent?.trim() === text,
    );

    if (!button) {
        throw new Error(`Button "${text}" not found`);
    }

    return button as HTMLElement;
}

async function tick() {
    await new Promise((resolve) => setTimeout(resolve, 0));
}

const { routerPatch } = vi.hoisted(() => ({ routerPatch: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { props: ['href'], template: '<a><slot /></a>' },
    router: { patch: routerPatch },
}));

const patient = { id: 'patient-1', name: 'Ana Souza' };

const confirmedAppointment = {
    id: 'appt-1',
    starts_at: '2026-09-10T12:00:00Z',
    ends_at: '2026-09-10T12:30:00Z',
    status: 'confirmed',
    status_label: 'Confirmado',
    professional_name: 'Dra. Ana',
    service_name: 'Consulta',
    unit_name: 'Unidade Centro',
};

const pendingRequest = {
    id: 'req-1',
    created_at: '2026-08-20T10:00:00Z',
    status: 'pending',
    status_label: 'Aguardando contato',
    professional_name: 'Dra. Ana',
    service_name: 'Avaliação estética',
    preferred_date: '2026-09-15',
    preferred_period: 'Manhã',
    notes: null,
};

describe('patient-portal/appointments/Index', () => {
    beforeEach(() => {
        routerPatch.mockClear();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('shows an empty state when there are neither appointments nor pending requests', () => {
        const wrapper = mount(Index, {
            props: { patient, appointments: [], pendingRequests: [] },
        });

        expect(wrapper.text()).toContain('Nenhum agendamento ainda');
    });

    it('lists pending appointment requests with their status and preference, separately from confirmed appointments', () => {
        const wrapper = mount(Index, {
            props: {
                patient,
                appointments: [confirmedAppointment],
                pendingRequests: [pendingRequest],
            },
        });

        const text = wrapper.text();
        expect(text).toContain('Pré-agendamentos aguardando confirmação');
        expect(text).toContain('Avaliação estética');
        expect(text).toContain('Com Dra. Ana');
        expect(text).toContain('Aguardando contato');
        expect(text).toContain('Manhã');

        // Confirmado aparece na seção separada, com o próprio rótulo.
        expect(text).toContain('Agendamentos confirmados');
        expect(text).toContain('Confirmado');
        expect(wrapper.text()).not.toContain('Nenhum agendamento ainda');
    });

    it('does not show either section heading when only one of the two lists has content', () => {
        const wrapper = mount(Index, {
            props: {
                patient,
                appointments: [confirmedAppointment],
                pendingRequests: [],
            },
        });

        expect(wrapper.text()).not.toContain(
            'Pré-agendamentos aguardando confirmação',
        );
        expect(wrapper.text()).not.toContain('Agendamentos confirmados');
    });

    it('falls back to a placeholder when the pending request has no service on file', () => {
        const wrapper = mount(Index, {
            props: {
                patient,
                appointments: [],
                pendingRequests: [{ ...pendingRequest, service_name: null }],
            },
        });

        expect(wrapper.text()).toContain('Serviço não informado');
    });

    it('cancels a pending request only after confirming in the dialog', async () => {
        const wrapper = mount(Index, {
            props: {
                patient,
                appointments: [],
                pendingRequests: [pendingRequest],
            },
            attachTo: document.body,
        });

        await wrapper.find('button').trigger('click');
        await tick();

        expect(routerPatch).not.toHaveBeenCalled();
        expect(document.body.textContent).toContain(
            'Cancelar pré-agendamento?',
        );

        findButton('Cancelar solicitação').click();
        await tick();

        expect(routerPatch).toHaveBeenCalledWith(
            `/portal/pacientes/${patient.id}/pre-agendamentos/${pendingRequest.id}/cancelar`,
            {},
            { preserveScroll: true },
        );
    });

    it('does not cancel a pending request when the dialog is dismissed', async () => {
        const wrapper = mount(Index, {
            props: {
                patient,
                appointments: [],
                pendingRequests: [pendingRequest],
            },
            attachTo: document.body,
        });

        await wrapper.find('button').trigger('click');
        await tick();

        findButton('Voltar').click();
        await tick();

        expect(routerPatch).not.toHaveBeenCalled();
    });

    it('cancels a confirmed appointment only after a reason is entered and confirmed', async () => {
        const wrapper = mount(Index, {
            props: {
                patient,
                appointments: [confirmedAppointment],
                pendingRequests: [],
            },
            attachTo: document.body,
        });

        const cancelTrigger = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Cancelar')!;
        await cancelTrigger.trigger('click');
        await tick();

        expect(document.body.textContent).toContain('Cancelar agendamento?');
        const confirmButton = findButton('Cancelar agendamento');
        expect(confirmButton.hasAttribute('disabled')).toBe(true);

        const textarea = document.body.querySelector(
            'textarea',
        ) as HTMLTextAreaElement;
        textarea.value = 'Imprevisto pessoal';
        textarea.dispatchEvent(new Event('input'));
        await tick();

        expect(confirmButton.hasAttribute('disabled')).toBe(false);
        confirmButton.click();
        await tick();

        expect(routerPatch).toHaveBeenCalledWith(
            `/portal/pacientes/${patient.id}/agendamentos/${confirmedAppointment.id}/cancelar`,
            { reason: 'Imprevisto pessoal' },
            { preserveScroll: true },
        );
    });

    it('hides a cancelled request from the main list behind a collapsed history toggle', async () => {
        const cancelledRequest = {
            ...pendingRequest,
            status: 'cancelled',
            status_label: 'Cancelado',
        };
        const wrapper = mount(Index, {
            props: {
                patient,
                appointments: [],
                pendingRequests: [cancelledRequest],
            },
        });

        expect(wrapper.text()).not.toContain(
            'Pré-agendamentos aguardando confirmação',
        );
        expect(wrapper.text()).not.toContain('Avaliação estética');
        expect(wrapper.text()).toContain('Ver histórico (1)');

        await wrapper.find('button').trigger('click');

        expect(wrapper.text()).toContain('Avaliação estética');
        expect(wrapper.text()).toContain('Ocultar histórico');
        expect(
            wrapper.findAll('button').find((b) => b.text() === 'Cancelar'),
        ).toBeUndefined();
    });

    it('keeps an active pending request visible alongside a collapsed history of cancelled ones', () => {
        const wrapper = mount(Index, {
            props: {
                patient,
                appointments: [],
                pendingRequests: [
                    pendingRequest,
                    {
                        ...pendingRequest,
                        id: 'req-2',
                        status: 'cancelled',
                        status_label: 'Cancelado',
                        service_name: 'Consulta antiga',
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain(
            'Pré-agendamentos aguardando confirmação',
        );
        expect(wrapper.text()).toContain('Avaliação estética');
        expect(wrapper.text()).not.toContain('Consulta antiga');
        expect(wrapper.text()).toContain('Ver histórico (1)');
    });
});

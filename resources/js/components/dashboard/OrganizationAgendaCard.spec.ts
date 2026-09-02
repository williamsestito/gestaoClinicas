import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import OrganizationAgendaCard from './OrganizationAgendaCard.vue';

const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>' },
    router: { get: getMock },
}));

function makeData(overrides: Partial<Record<string, unknown>> = {}) {
    return {
        date: '2026-08-17',
        professionalId: null,
        professionals: [
            { id: 'prof-1', display_name: 'Dra Juliana Cruz' },
            { id: 'prof-2', display_name: 'Dr João Paiva' },
        ],
        appointments: [
            {
                id: 'apt-1',
                starts_at: '2026-08-17T12:00:00Z',
                ends_at: '2026-08-17T12:30:00Z',
                status: 'confirmed',
                status_label: 'Confirmado',
                professional_name: 'Dra Juliana Cruz',
                patient_name: 'Ana Souza',
                service_name: 'Consulta',
                unit_name: 'Matriz',
            },
        ],
        ...overrides,
    };
}

describe('OrganizationAgendaCard', () => {
    it('lists the appointments for the given day', () => {
        const wrapper = mount(OrganizationAgendaCard, {
            props: { data: makeData() },
        });

        expect(wrapper.text()).toContain('Ana Souza');
        expect(wrapper.text()).toContain('Dra Juliana Cruz');
        expect(wrapper.text()).toContain('Consulta');
        expect(wrapper.text()).toContain('Confirmado');
    });

    it('shows an empty state when there are no appointments that day', () => {
        const wrapper = mount(OrganizationAgendaCard, {
            props: { data: makeData({ appointments: [] }) },
        });

        expect(wrapper.text()).toContain('Nenhum agendamento neste dia.');
    });

    it('lists the professionals in the filter, with "Todos" first', () => {
        const wrapper = mount(OrganizationAgendaCard, {
            props: { data: makeData() },
        });

        const options = wrapper
            .find('select')
            .findAll('option')
            .map((option) => option.text());
        expect(options).toEqual([
            'Todos os profissionais',
            'Dra Juliana Cruz',
            'Dr João Paiva',
        ]);
    });

    it('reloads only orgAgenda when a professional is selected', async () => {
        const wrapper = mount(OrganizationAgendaCard, {
            props: { data: makeData() },
        });

        await wrapper.find('select').setValue('prof-2');

        expect(getMock).toHaveBeenCalledWith(
            '/dashboard',
            { agenda_date: '2026-08-17', agenda_professional_id: 'prof-2' },
            expect.objectContaining({ only: ['orgAgenda'] }),
        );
    });

    it('reloads with the previous/next day when navigating', async () => {
        const wrapper = mount(OrganizationAgendaCard, {
            props: { data: makeData() },
        });

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Próximo')
            ?.trigger('click');

        expect(getMock).toHaveBeenCalledWith(
            '/dashboard',
            expect.objectContaining({ agenda_date: '2026-08-18' }),
            expect.objectContaining({ only: ['orgAgenda'] }),
        );

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Anterior')
            ?.trigger('click');

        expect(getMock).toHaveBeenCalledWith(
            '/dashboard',
            expect.objectContaining({ agenda_date: '2026-08-16' }),
            expect.objectContaining({ only: ['orgAgenda'] }),
        );
    });

    it('jumps directly to a chosen date via the date input', async () => {
        const wrapper = mount(OrganizationAgendaCard, {
            props: { data: makeData() },
        });

        const dateInput = wrapper.find('input[type="date"]');
        expect((dateInput.element as HTMLInputElement).value).toBe(
            '2026-08-17',
        );

        await dateInput.setValue('2026-09-05');

        expect(getMock).toHaveBeenCalledWith(
            '/dashboard',
            expect.objectContaining({ agenda_date: '2026-09-05' }),
            expect.objectContaining({ only: ['orgAgenda'] }),
        );
    });

    it('preserves the currently selected professional when navigating dates', async () => {
        const wrapper = mount(OrganizationAgendaCard, {
            props: { data: makeData({ professionalId: 'prof-1' }) },
        });

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Hoje')
            ?.trigger('click');

        expect(getMock).toHaveBeenCalledWith(
            '/dashboard',
            expect.objectContaining({ agenda_professional_id: 'prof-1' }),
            expect.objectContaining({ only: ['orgAgenda'] }),
        );
    });
});

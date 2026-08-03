import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Agendas from './Agendas.vue';
import type { AgendaRow } from './Agendas.vue';

const { routerMock } = vi.hoisted(() => ({
    routerMock: {
        get: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>' },
    router: routerMock,
}));

function makeRow(overrides: Partial<AgendaRow> = {}): AgendaRow {
    return {
        id: '1',
        display_name: 'Dra. Ana Souza',
        status: 'active',
        operational_status: 'operational',
        primary_specialty_name: 'Cardiologia',
        unit_ids: ['unit-1'],
        unit_names: ['Unidade Centro'],
        specialty_ids: ['spec-1'],
        service_ids: [],
        weekdays: [1, 2, 3],
        vigency_from: '2026-08-01',
        vigency_until: '2026-08-30',
        working_hours_count: 3,
        has_working_hours: true,
        ongoing_time_blocks_count: 0,
        has_ongoing_absence: false,
        has_conflict_alert: false,
        ...overrides,
    };
}

describe('settings/professionals/Agendas', () => {
    it('shows an empty state when there are no professionals', () => {
        const wrapper = mount(Agendas, { props: { professionals: [] } });

        expect(wrapper.text()).toContain(
            'Nenhum profissional ativo cadastrado ainda.',
        );
    });

    it('renders weekdays, vigency and conflict alerts', () => {
        const wrapper = mount(Agendas, {
            props: {
                professionals: [
                    makeRow({ display_name: 'Dra. Ana Souza' }),
                    makeRow({
                        id: '2',
                        display_name: 'Dr. Bruno Lima',
                        has_conflict_alert: true,
                        weekdays: [],
                        working_hours_count: 0,
                        has_working_hours: false,
                    }),
                ],
            },
        });

        expect(wrapper.text()).toContain('Seg, Ter, Qua');
        expect(wrapper.text()).toContain('01/08/2026 até 30/08/2026');
        expect(wrapper.text()).toContain('sem jornada');
    });

    it('filters by unit', async () => {
        const wrapper = mount(Agendas, {
            props: {
                professionals: [
                    makeRow({
                        id: '1',
                        display_name: 'Dra. Ana Souza',
                        unit_ids: ['unit-1'],
                    }),
                    makeRow({
                        id: '2',
                        display_name: 'Dr. Bruno Lima',
                        unit_ids: ['unit-2'],
                    }),
                ],
                units: [
                    { id: 'unit-1', name: 'Unidade Centro' },
                    { id: 'unit-2', name: 'Unidade Norte' },
                ],
            },
        });

        await wrapper
            .find('select[aria-label="Filtrar agendas por unidade"]')
            .setValue('unit-1');

        expect(wrapper.text()).toContain('Dra. Ana Souza');
        expect(wrapper.text()).not.toContain('Dr. Bruno Lima');
    });

    it('filters by ongoing block presence', async () => {
        const wrapper = mount(Agendas, {
            props: {
                professionals: [
                    makeRow({
                        id: '1',
                        display_name: 'Dra. Ana Souza',
                        has_ongoing_absence: true,
                    }),
                    makeRow({
                        id: '2',
                        display_name: 'Dr. Bruno Lima',
                        has_ongoing_absence: false,
                    }),
                ],
            },
        });

        await wrapper
            .find(
                'select[aria-label="Filtrar agendas por bloqueio em andamento"]',
            )
            .setValue('with');

        expect(wrapper.text()).toContain('Dra. Ana Souza');
        expect(wrapper.text()).not.toContain('Dr. Bruno Lima');
    });

    it('filters by period, only matching professionals whose vigency intersects it', async () => {
        const wrapper = mount(Agendas, {
            props: {
                professionals: [
                    makeRow({
                        id: '1',
                        display_name: 'Dra. Ana Souza',
                        vigency_from: '2026-08-01',
                        vigency_until: '2026-08-30',
                    }),
                    makeRow({
                        id: '2',
                        display_name: 'Dr. Bruno Lima',
                        vigency_from: '2026-01-01',
                        vigency_until: '2026-01-31',
                    }),
                ],
            },
        });

        await wrapper
            .find(
                'input[aria-label="Filtrar agendas por data inicial do período"]',
            )
            .setValue('2026-08-01');

        expect(wrapper.text()).toContain('Dra. Ana Souza');
        expect(wrapper.text()).not.toContain('Dr. Bruno Lima');
    });

    it('navigates to the professional availability page when a row is clicked', async () => {
        const wrapper = mount(Agendas, {
            props: { professionals: [makeRow({ id: '42' })] },
        });

        await wrapper.find('tbody tr').trigger('click');

        expect(routerMock.get).toHaveBeenCalledWith(
            '/settings/professionals/42/availability',
        );
    });
});

import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import MedicalRecordHistory from './MedicalRecordHistory.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
}));

function makeRecords(overrides: Partial<Record<string, unknown>> = {}) {
    return {
        data: [
            {
                id: 'rec-1',
                appointment_id: 'apt-1',
                status: 'finalized' as const,
                status_label: 'Finalizado',
                professional_name: 'Dra. Beatriz',
                appointment_starts_at: '2026-09-01T12:00:00Z',
                finalized_at: '2026-09-01T13:00:00Z',
            },
        ],
        links: [],
        total: 1,
        ...overrides,
    };
}

describe('settings/my-patients/MedicalRecordHistory', () => {
    it('shows an empty state when there are no records', () => {
        const wrapper = mount(MedicalRecordHistory, {
            props: {
                patient: { id: 'patient-1', name: 'Ana Souza' },
                records: makeRecords({ data: [], total: 0 }),
            },
        });

        expect(wrapper.text()).toContain('Nenhum prontuário registrado');
    });

    it('lists records with a link to the record show page', () => {
        const wrapper = mount(MedicalRecordHistory, {
            props: {
                patient: { id: 'patient-1', name: 'Ana Souza' },
                records: makeRecords(),
            },
        });

        expect(wrapper.text()).toContain('Ana Souza');
        expect(wrapper.text()).toContain('Dra. Beatriz');
        expect(wrapper.text()).toContain('Finalizado');

        const link = wrapper
            .findAll('a')
            .find((a) => a.text() === 'Ver prontuário');
        expect(link?.attributes('href')).toContain('apt-1');
    });

    it('shows a draft badge and a dash for an unfinalized record', () => {
        const wrapper = mount(MedicalRecordHistory, {
            props: {
                patient: { id: 'patient-1', name: 'Ana Souza' },
                records: makeRecords({
                    data: [
                        {
                            id: 'rec-2',
                            appointment_id: 'apt-2',
                            status: 'draft',
                            status_label: 'Rascunho',
                            professional_name: null,
                            appointment_starts_at: '2026-09-05T12:00:00Z',
                            finalized_at: null,
                        },
                    ],
                }),
            },
        });

        expect(wrapper.text()).toContain('Rascunho');
        expect(wrapper.text()).toContain('—');
    });
});

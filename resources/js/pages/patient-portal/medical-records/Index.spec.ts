import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Index from './Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
}));

const patient = { id: 'patient-1', name: 'Ana Souza' };

function makeRecord(overrides: Partial<Record<string, unknown>> = {}) {
    return {
        id: 'rec-1',
        appointment_starts_at: '2026-09-01T12:00:00Z',
        professional_name: 'Dra. Beatriz',
        anamnesis: 'Paciente relata dor leve.',
        evaluation: 'Boa evolução.',
        treatment_plan: null,
        evolution_notes: null,
        prescriptions: null,
        referrals: null,
        has_return_right: true,
        return_window_days: 15,
        finalized_at: '2026-09-01T13:00:00Z',
        addenda: [],
        files: [],
        ...overrides,
    };
}

describe('patient-portal/medical-records/Index', () => {
    it('shows an empty state when there are no released records', () => {
        const wrapper = mount(Index, {
            props: { patient, records: [] },
        });

        expect(wrapper.text()).toContain('Nenhum prontuário disponível');
    });

    it('renders a released record with its clinical sections and return right', () => {
        const wrapper = mount(Index, {
            props: { patient, records: [makeRecord()] },
        });

        expect(wrapper.text()).toContain('Ana Souza');
        expect(wrapper.text()).toContain('Dra. Beatriz');
        expect(wrapper.text()).toContain('Paciente relata dor leve.');
        expect(wrapper.text()).toContain('Boa evolução.');
        expect(wrapper.text()).toContain('Direito a retorno');
        expect(wrapper.text()).toContain('15 dias');
    });

    it('renders addenda and files when present', () => {
        const wrapper = mount(Index, {
            props: {
                patient,
                records: [
                    makeRecord({
                        addenda: [
                            {
                                id: 'add-1',
                                body: 'Correção registrada.',
                                created_at: '2026-09-02T10:00:00Z',
                            },
                        ],
                        files: [
                            {
                                id: 'file-1',
                                category_label: 'Exame',
                                original_filename: 'exame.pdf',
                            },
                        ],
                    }),
                ],
            },
        });

        expect(wrapper.text()).toContain('Correção registrada.');
        expect(wrapper.text()).toContain('Exame');
        expect(wrapper.text()).toContain('exame.pdf');
    });

    it('does not render a section that has no content', () => {
        const wrapper = mount(Index, {
            props: {
                patient,
                records: [makeRecord({ evaluation: null })],
            },
        });

        expect(wrapper.text()).not.toContain('Avaliação');
    });
});

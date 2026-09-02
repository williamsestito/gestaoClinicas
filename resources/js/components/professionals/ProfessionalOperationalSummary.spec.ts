import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { OperationalSummary } from './ProfessionalOperationalSummary.vue';
import ProfessionalOperationalSummary from './ProfessionalOperationalSummary.vue';

function makeSummary(
    overrides: Partial<OperationalSummary> = {},
): OperationalSummary {
    return {
        is_operational: true,
        status: 'operational',
        status_label: 'Operacional',
        reasons: [],
        warnings: [],
        primary_unit: { id: 'unit-1', name: 'Unidade Centro' },
        active_units_count: 1,
        primary_specialty: { id: 'sp-1', name: 'Cardiologia' },
        specialties_count: 1,
        primary_registration: {
            council: 'CRM',
            masked_number: '••••1234',
            validity_status: 'valid',
        },
        active_services_count: 2,
        has_working_hours: true,
        next_time_block: null,
        ...overrides,
    };
}

describe('ProfessionalOperationalSummary', () => {
    it('shows "Não definida"/"Não definido" when there is no primary unit, specialty or registration', () => {
        const wrapper = mount(ProfessionalOperationalSummary, {
            props: {
                summary: makeSummary({
                    primary_unit: null,
                    primary_specialty: null,
                    primary_registration: null,
                }),
            },
        });

        expect(wrapper.text()).toContain('Não definida');
        expect(wrapper.text()).toContain('Não definido');
    });

    it('shows the operational status label', () => {
        const wrapper = mount(ProfessionalOperationalSummary, {
            props: { summary: makeSummary({ status_label: 'Operacional' }) },
        });

        expect(wrapper.text()).toContain('Operacional');
    });

    it('shows configuration warnings without calling them errors', () => {
        const wrapper = mount(ProfessionalOperationalSummary, {
            props: {
                summary: makeSummary({
                    warnings: [
                        'Este profissional ainda não possui serviço ativo.',
                    ],
                }),
            },
        });

        expect(wrapper.text()).toContain(
            'Este profissional ainda não possui serviço ativo.',
        );
        expect(wrapper.text()).not.toContain('erro');
    });

    it('shows the next time block when present', () => {
        const wrapper = mount(ProfessionalOperationalSummary, {
            props: {
                summary: makeSummary({
                    next_time_block: {
                        type: 'vacation',
                        starts_at: '2026-09-01T03:00:00.000000Z',
                    },
                }),
            },
        });

        expect(wrapper.text()).toContain('Próxima ausência');
        expect(wrapper.text()).toContain('Férias');
    });

    it('does not show the "próxima ausência" line when there is none', () => {
        const wrapper = mount(ProfessionalOperationalSummary, {
            props: { summary: makeSummary({ next_time_block: null }) },
        });

        expect(wrapper.text()).not.toContain('Próxima ausência');
    });
});

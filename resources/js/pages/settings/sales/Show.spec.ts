import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Show from './Show.vue';

const { patchMock } = vi.hoisted(() => ({ patchMock: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    useForm: (initial: Record<string, unknown>) => {
        const form = {
            ...initial,
            errors: {},
            processing: false,
            patch: patchMock,
            reset() {
                Object.keys(initial).forEach((key) => {
                    (form as Record<string, unknown>)[key] = '';
                });
            },
        };

        return form;
    },
}));

function makeSale(overrides: Partial<Record<string, unknown>> = {}) {
    return {
        id: 'sale-1',
        status: 'draft',
        status_label: 'Rascunho',
        patient_name: 'Ana Souza',
        unit_name: 'Unidade Centro',
        legal_entity_name: 'Clínica LTDA',
        professional_name: null,
        subtotal_cents: 10000,
        discount_total_cents: 0,
        total_cents: 10000,
        cancellation_reason: null,
        created_at: '2026-09-01T12:00:00Z',
        items: [
            {
                id: 'item-1',
                item_type: 'service',
                item_type_label: 'Serviço',
                label: 'Massagem',
                session_count: null,
                quantity: 1,
                unit_price_cents: 10000,
                discount_percentage: 0,
                final_price_cents: 10000,
                requires_approval: false,
                is_pending_approval: false,
                approver_name: null,
                approved_at: null,
                approval_justification: null,
            },
        ],
        ...overrides,
    };
}

describe('settings/sales/Show', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('shows our own confirmation dialog (not a native browser alert) and confirms the sale', async () => {
        const wrapper = mount(Show, {
            props: {
                sale: makeSale(),
                canEdit: true,
                canConfirm: true,
                canCancel: false,
                canApproveDiscount: false,
            },
            attachTo: document.body,
        });

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Confirmar venda')
            ?.trigger('click');
        await wrapper.vm.$nextTick();

        const dialogText = document.body.textContent ?? '';
        expect(dialogText).toContain('Confirmar venda?');

        const confirmButton = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) => button.textContent?.trim() === 'Confirmar');
        confirmButton?.dispatchEvent(new Event('click'));
        await wrapper.vm.$nextTick();

        expect(patchMock).toHaveBeenCalledWith(
            expect.stringContaining('/confirmar'),
            expect.objectContaining({ preserveScroll: true }),
        );

        wrapper.unmount();
    });

    it('does not confirm the sale when the confirmation dialog is dismissed', async () => {
        const wrapper = mount(Show, {
            props: {
                sale: makeSale(),
                canEdit: true,
                canConfirm: true,
                canCancel: false,
                canApproveDiscount: false,
            },
            attachTo: document.body,
        });

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Confirmar venda')
            ?.trigger('click');
        await wrapper.vm.$nextTick();

        const cancelButton = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) => button.textContent?.trim() === 'Voltar');
        cancelButton?.dispatchEvent(new Event('click'));
        await wrapper.vm.$nextTick();

        expect(patchMock).not.toHaveBeenCalled();

        wrapper.unmount();
    });

    it('shows a pending-approval banner with an approve button for an item awaiting approval', () => {
        const wrapper = mount(Show, {
            props: {
                sale: makeSale({
                    status: 'pending_approval',
                    status_label: 'Aguardando aprovação',
                    items: [
                        {
                            id: 'item-1',
                            item_type: 'service',
                            item_type_label: 'Serviço',
                            label: 'Massagem',
                            session_count: null,
                            quantity: 1,
                            unit_price_cents: 10000,
                            discount_percentage: 50,
                            final_price_cents: 5000,
                            requires_approval: true,
                            is_pending_approval: true,
                            approver_name: null,
                            approved_at: null,
                            approval_justification: null,
                        },
                    ],
                }),
                canEdit: true,
                canConfirm: false,
                canCancel: false,
                canApproveDiscount: true,
            },
        });

        expect(wrapper.text()).toContain('Aguardando aprovação de desconto');
        expect(
            wrapper.findAll('button').some((b) => b.text() === 'Aprovar'),
        ).toBe(true);
    });

    it('opens the approval dialog and submits justification and password', async () => {
        const wrapper = mount(Show, {
            props: {
                sale: makeSale({
                    status: 'pending_approval',
                    items: [
                        {
                            id: 'item-1',
                            item_type: 'service',
                            item_type_label: 'Serviço',
                            label: 'Massagem',
                            session_count: null,
                            quantity: 1,
                            unit_price_cents: 10000,
                            discount_percentage: 50,
                            final_price_cents: 5000,
                            requires_approval: true,
                            is_pending_approval: true,
                            approver_name: null,
                            approved_at: null,
                            approval_justification: null,
                        },
                    ],
                }),
                canEdit: true,
                canConfirm: false,
                canCancel: false,
                canApproveDiscount: true,
            },
        });

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Aprovar')
            ?.trigger('click');

        expect(wrapper.find('#approval-justification').exists()).toBe(true);

        await wrapper.find('#approval-justification').setValue('Ok.');
        await wrapper.find('#approval-password').setValue('password');
        await wrapper.find('form').trigger('submit');

        expect(patchMock).toHaveBeenCalledWith(
            expect.stringContaining('aprovar-desconto'),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows the cancellation reason for a cancelled sale', () => {
        const wrapper = mount(Show, {
            props: {
                sale: makeSale({
                    status: 'cancelled',
                    status_label: 'Cancelada',
                    cancellation_reason: 'Paciente desistiu.',
                }),
                canEdit: false,
                canConfirm: false,
                canCancel: false,
                canApproveDiscount: false,
            },
        });

        expect(wrapper.text()).toContain('Paciente desistiu.');
    });
});

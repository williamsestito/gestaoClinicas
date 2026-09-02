import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PatientSummaryModal from './PatientSummaryModal.vue';

const fullAccessResponse = {
    full_access: true,
    can_view_medical_record: true,
    patient: {
        id: 'patient-1',
        name: 'Ana Souza',
        preferred_name: null,
        phone: '(47) 99999-0000',
        email: 'ana@example.com',
        birth_date: '1990-01-01',
        document: '123.456.789-00',
        status: 'active',
    },
    appointments_by_professional: [
        {
            professional_name: 'Dra Juliana Cruz',
            appointments: [
                {
                    id: 'apt-1',
                    starts_at: '2026-08-17T12:00:00Z',
                    status_label: 'Concluído',
                    service_name: 'Consulta',
                },
            ],
        },
    ],
    pending_requests: [],
};

const summaryOnlyResponse = {
    full_access: false,
    can_view_medical_record: false,
    patient: {
        id: 'patient-2',
        name: 'Bruno Lima',
        preferred_name: null,
        phone: '(47) 98888-0000',
        email: null,
        birth_date: null,
        document: null,
        status: 'active',
    },
    appointments_by_professional: [],
    pending_requests: [
        {
            id: 'req-1',
            professional_name: 'Dra Juliana Cruz',
            status_label: 'Aguardando contato',
            created_at: '2026-08-17T10:00:00Z',
        },
    ],
};

describe('PatientSummaryModal', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('stays closed and never fetches when modelValue is null', () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(PatientSummaryModal, {
            props: { modelValue: null },
        });

        expect(fetchMock).not.toHaveBeenCalled();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('fetches and shows the full summary, grouped by professional, when full access is granted', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => fullAccessResponse,
            }),
        );

        const wrapper = mount(PatientSummaryModal, {
            props: { modelValue: null },
        });
        await wrapper.setProps({ modelValue: 'patient-1' });
        await flushPromises();

        expect(document.body.textContent).toContain('Ana Souza');
        expect(document.body.textContent).toContain('ana@example.com');
        expect(document.body.textContent).toContain('Dra Juliana Cruz');
        expect(document.body.textContent).toContain('Consulta');
        expect(document.body.textContent).toContain('Editar cadastro completo');
        expect(document.body.textContent).toContain('Prontuário');

        wrapper.unmount();
    });

    it('shows only the essentials and hides edit/medical-record actions for summary-only access', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => summaryOnlyResponse,
            }),
        );

        const wrapper = mount(PatientSummaryModal, {
            props: { modelValue: null },
        });
        await wrapper.setProps({ modelValue: 'patient-2' });
        await flushPromises();

        expect(document.body.textContent).toContain('Bruno Lima');
        expect(document.body.textContent).toContain('Aguardando contato');
        expect(document.body.textContent).not.toContain(
            'Editar cadastro completo',
        );
        expect(document.body.textContent).not.toContain('Prontuário');

        wrapper.unmount();
    });

    it('shows an error message when the request fails (e.g. 403)', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({ ok: false, status: 403 }),
        );

        const wrapper = mount(PatientSummaryModal, {
            props: { modelValue: null },
        });
        await wrapper.setProps({ modelValue: 'patient-3' });
        await flushPromises();

        expect(document.body.textContent).toContain(
            'Não foi possível carregar os detalhes deste paciente.',
        );

        wrapper.unmount();
    });

    it('emits update:modelValue(null) when closed', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => fullAccessResponse,
            }),
        );

        const wrapper = mount(PatientSummaryModal, {
            props: { modelValue: null },
        });
        await wrapper.setProps({ modelValue: 'patient-1' });
        await flushPromises();

        const closeButton = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) => button.textContent?.trim() === 'Fechar');
        closeButton?.dispatchEvent(new Event('click', { bubbles: true }));
        await flushPromises();

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null]);

        wrapper.unmount();
    });
});

async function flushPromises(): Promise<void> {
    await new Promise((resolve) => setTimeout(resolve, 0));
    await new Promise((resolve) => setTimeout(resolve, 0));
}

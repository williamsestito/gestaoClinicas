import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Show from './Show.vue';

const { patchMock, postMock, resetMock } = vi.hoisted(() => ({
    patchMock: vi.fn(),
    postMock: vi.fn(),
    resetMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    useForm: (initial: Record<string, unknown>) => {
        const form = {
            ...initial,
            errors: {},
            processing: false,
            patch: patchMock,
            post: postMock,
            reset: resetMock,
            transform() {
                return form;
            },
        };

        return form;
    },
}));

const baseAppointment = {
    id: 'apt-1',
    starts_at: '2026-09-01T12:00:00Z',
    status: 'completed',
    status_label: 'Concluído',
};

function makeMedicalRecord(overrides: Partial<Record<string, unknown>> = {}) {
    return {
        id: 'rec-1',
        status: 'draft' as const,
        status_label: 'Rascunho',
        patient_name: 'Ana Souza',
        professional_name: 'Dra. Beatriz',
        anamnesis: null,
        preexisting_conditions: null,
        allergies: null,
        current_medications: null,
        contraindications: null,
        evaluation: null,
        treatment_plan: null,
        procedures_performed: null,
        evolution_notes: null,
        prescriptions: null,
        referrals: null,
        has_return_right: false,
        return_window_days: null,
        finalized_at: null,
        released_to_patient_at: null,
        addenda: [],
        files: [],
        ...overrides,
    };
}

describe('settings/medical-records/Show', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.stubGlobal(
            'confirm',
            vi.fn(() => true),
        );
    });

    it('shows editable fields and a "Salvar rascunho" button for a draft record', async () => {
        const wrapper = mount(Show, {
            props: {
                appointment: baseAppointment,
                medicalRecord: makeMedicalRecord(),
                canEdit: true,
                canFinalize: true,
                canRelease: false,
                canAddAddendum: false,
            },
        });

        const anamnesis = wrapper.find('#mr-anamnesis');
        expect((anamnesis.element as HTMLTextAreaElement).disabled).toBe(false);

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Salvar rascunho')
            ?.trigger('click');

        expect(patchMock).toHaveBeenCalledWith(
            expect.stringContaining('rec-1'),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('asks for confirmation before finalizing, then submits', async () => {
        const wrapper = mount(Show, {
            props: {
                appointment: baseAppointment,
                medicalRecord: makeMedicalRecord(),
                canEdit: true,
                canFinalize: true,
                canRelease: false,
                canAddAddendum: false,
            },
        });

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Finalizar prontuário')
            ?.trigger('click');

        expect(patchMock).toHaveBeenCalledWith(
            expect.stringContaining('/finalizar'),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('does not finalize when the confirmation is declined', async () => {
        vi.stubGlobal(
            'confirm',
            vi.fn(() => false),
        );
        const wrapper = mount(Show, {
            props: {
                appointment: baseAppointment,
                medicalRecord: makeMedicalRecord(),
                canEdit: true,
                canFinalize: true,
                canRelease: false,
                canAddAddendum: false,
            },
        });

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Finalizar prontuário')
            ?.trigger('click');

        expect(patchMock).not.toHaveBeenCalled();
    });

    it('disables the fields and shows addenda for a finalized record', () => {
        const wrapper = mount(Show, {
            props: {
                appointment: baseAppointment,
                medicalRecord: makeMedicalRecord({
                    status: 'finalized',
                    status_label: 'Finalizado',
                    anamnesis: 'Conteúdo já finalizado.',
                    addenda: [
                        {
                            id: 'add-1',
                            body: 'Correção registrada.',
                            author_name: 'Dr. João',
                            created_at: '2026-09-02T10:00:00Z',
                        },
                    ],
                }),
                canEdit: false,
                canFinalize: false,
                canRelease: true,
                canAddAddendum: true,
            },
        });

        const anamnesis = wrapper.find('#mr-anamnesis');
        expect((anamnesis.element as HTMLTextAreaElement).disabled).toBe(true);
        expect(wrapper.text()).toContain('Correção registrada.');
        expect(
            wrapper
                .findAll('button')
                .find((button) => button.text() === 'Salvar rascunho'),
        ).toBeUndefined();
    });

    it('submits a new addendum', async () => {
        const wrapper = mount(Show, {
            props: {
                appointment: baseAppointment,
                medicalRecord: makeMedicalRecord({ status: 'finalized' }),
                canEdit: false,
                canFinalize: false,
                canRelease: false,
                canAddAddendum: true,
            },
        });

        const textarea = wrapper.find('#mr-addendum');
        await textarea.setValue('Nova observação.');
        await wrapper.find('form').trigger('submit');

        expect(postMock).toHaveBeenCalledWith(
            expect.stringContaining('/adendos'),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows "Liberar prontuário" only when releasable and not yet released', () => {
        const releasable = mount(Show, {
            props: {
                appointment: baseAppointment,
                medicalRecord: makeMedicalRecord({ status: 'finalized' }),
                canEdit: false,
                canFinalize: false,
                canRelease: true,
                canAddAddendum: false,
            },
        });
        expect(releasable.text()).toContain(
            'Liberar prontuário para o paciente',
        );

        const alreadyReleased = mount(Show, {
            props: {
                appointment: baseAppointment,
                medicalRecord: makeMedicalRecord({
                    status: 'finalized',
                    released_to_patient_at: '2026-09-02T10:00:00Z',
                }),
                canEdit: false,
                canFinalize: false,
                canRelease: true,
                canAddAddendum: false,
            },
        });
        expect(alreadyReleased.text()).not.toContain(
            'Liberar prontuário para o paciente',
        );
        expect(alreadyReleased.text()).toContain('Liberado ao paciente');
    });

    it('lists attached files and disables the upload button without a selected file', () => {
        const wrapper = mount(Show, {
            props: {
                appointment: baseAppointment,
                medicalRecord: makeMedicalRecord({
                    files: [
                        {
                            id: 'file-1',
                            category: 'exam',
                            category_label: 'Exame',
                            original_filename: 'exame.pdf',
                            uploaded_by_name: 'Dra. Beatriz',
                            created_at: '2026-09-01T12:00:00Z',
                        },
                    ],
                }),
                canEdit: true,
                canFinalize: true,
                canRelease: false,
                canAddAddendum: false,
            },
        });

        expect(wrapper.text()).toContain('exame.pdf');
        const uploadButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Enviar');
        expect(uploadButton?.attributes('disabled')).toBeDefined();
    });

    it('opens the file picker via the "Selecionar arquivo" button, hiding the native input', async () => {
        const wrapper = mount(Show, {
            props: {
                appointment: baseAppointment,
                medicalRecord: makeMedicalRecord(),
                canEdit: true,
                canFinalize: true,
                canRelease: false,
                canAddAddendum: false,
            },
        });

        const input = wrapper.find('#mr-file').element as HTMLInputElement;
        const clickSpy = vi.spyOn(input, 'click');
        expect(input.className).toContain('hidden');

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Selecionar arquivo'))
            ?.trigger('click');

        expect(clickSpy).toHaveBeenCalledOnce();
    });
});

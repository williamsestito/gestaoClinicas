import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import ConfirmDialog from './ConfirmDialog.vue';

afterEach(() => {
    document.body.innerHTML = '';
});

describe('ConfirmDialog', () => {
    it('renders nothing visible when closed', async () => {
        mount(ConfirmDialog, {
            props: { open: false, title: 'Excluir item?' },
            attachTo: document.body,
        });
        await nextTick();

        expect(document.body.querySelector('[role="dialog"]')).toBeNull();
    });

    it('shows the title and description when open', async () => {
        mount(ConfirmDialog, {
            props: {
                open: true,
                title: 'Excluir Ana Souza?',
                description: 'Isso inativa o cadastro, não apaga fisicamente.',
            },
            attachTo: document.body,
        });
        await nextTick();

        const dialog = document.body.querySelector('[role="dialog"]');
        expect(dialog?.textContent).toContain('Excluir Ana Souza?');
        expect(dialog?.textContent).toContain(
            'Isso inativa o cadastro, não apaga fisicamente.',
        );
    });

    it('emits confirm and closes when the confirm button is clicked', async () => {
        const wrapper = mount(ConfirmDialog, {
            props: { open: true, title: 'Excluir?', confirmLabel: 'Excluir' },
            attachTo: document.body,
        });
        await nextTick();

        const confirmButton = Array.from(
            document.body.querySelectorAll('[role="dialog"] button'),
        ).find((b) => b.textContent === 'Excluir') as HTMLElement;
        confirmButton.click();
        await nextTick();

        expect(wrapper.emitted('confirm')).toHaveLength(1);
        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('only closes, without emitting confirm, when the cancel button is clicked', async () => {
        const wrapper = mount(ConfirmDialog, {
            props: { open: true, title: 'Excluir?', cancelLabel: 'Cancelar' },
            attachTo: document.body,
        });
        await nextTick();

        const cancelButton = Array.from(
            document.body.querySelectorAll('[role="dialog"] button'),
        ).find((b) => b.textContent === 'Cancelar') as HTMLElement;
        cancelButton.click();
        await nextTick();

        expect(wrapper.emitted('confirm')).toBeUndefined();
        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('applies the destructive button style when the destructive prop is set', async () => {
        mount(ConfirmDialog, {
            props: {
                open: true,
                title: 'Excluir?',
                confirmLabel: 'Excluir',
                destructive: true,
            },
            attachTo: document.body,
        });
        await nextTick();

        const confirmButton = Array.from(
            document.body.querySelectorAll('[role="dialog"] button'),
        ).find((b) => b.textContent === 'Excluir') as HTMLElement;

        expect(confirmButton.className).toContain('destructive');
    });
});

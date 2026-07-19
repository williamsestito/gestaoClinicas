import { DOMWrapper, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import type { Unit } from '@/types/organization';
import UnitRowActions from './UnitRowActions.vue';

const baseUnit: Unit = {
    id: '1',
    organization_id: 'org-1',
    legal_entity_id: 'le-1',
    name: 'Unidade Centro',
    code: 'UN-0001',
    slug: 'unidade-centro',
    status: 'active',
    is_headquarters: false,
    timezone: 'America/Sao_Paulo',
    email: null,
    phone: null,
    whatsapp: null,
    deleted_at: null,
};

// O conteúdo do DropdownMenu é renderizado via Teleport para document.body
// (fora da árvore do wrapper), então a interação e as asserções precisam
// mirar no documento real, não apenas no wrapper.
function mountAttached(unit: Unit) {
    return mount(UnitRowActions, {
        props: { unit },
        attachTo: document.body,
    });
}

async function openMenu(wrapper: ReturnType<typeof mountAttached>) {
    await wrapper.find('button').trigger('click');
    await new Promise((resolve) => setTimeout(resolve, 0));
}

describe('UnitRowActions', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('shows editar, ativar/inativar, definir como matriz and excluir for an active, non-headquarters unit', async () => {
        const wrapper = mountAttached(baseUnit);
        await openMenu(wrapper);

        const text = document.body.textContent ?? '';
        expect(text).toContain('Editar');
        expect(text).toContain('Inativar');
        expect(text).toContain('Definir como matriz');
        expect(text).toContain('Excluir');
        expect(text).not.toContain('Restaurar');
    });

    it('shows "Ativar" instead of "Inativar" for an inactive unit', async () => {
        const wrapper = mountAttached({ ...baseUnit, status: 'inactive' });
        await openMenu(wrapper);

        const text = document.body.textContent ?? '';
        expect(text).toContain('Ativar');
        expect(text).not.toContain('Inativar');
    });

    it('hides "Definir como matriz" for an inactive unit', async () => {
        const wrapper = mountAttached({ ...baseUnit, status: 'inactive' });
        await openMenu(wrapper);

        expect(document.body.textContent ?? '').not.toContain(
            'Definir como matriz',
        );
    });

    it('hides "Definir como matriz" and "Excluir" for the headquarters unit', async () => {
        const wrapper = mountAttached({ ...baseUnit, is_headquarters: true });
        await openMenu(wrapper);

        const text = document.body.textContent ?? '';
        expect(text).not.toContain('Definir como matriz');
        expect(text).not.toContain('Excluir');
        expect(text).toContain('Editar');
    });

    it('shows only "Restaurar" for a soft-deleted unit', async () => {
        const wrapper = mountAttached({
            ...baseUnit,
            deleted_at: '2026-07-19T12:00:00Z',
        });
        await openMenu(wrapper);

        const text = document.body.textContent ?? '';
        expect(text).toContain('Restaurar');
        expect(text).not.toContain('Editar');
        expect(text).not.toContain('Excluir');
        expect(text).not.toContain('Ativar');
        expect(text).not.toContain('Inativar');
    });

    it('emits the corresponding event when each action is selected', async () => {
        const wrapper = mountAttached(baseUnit);
        await openMenu(wrapper);

        const editItem = Array.from(
            document.body.querySelectorAll('[role="menuitem"]'),
        ).find((item) => item.textContent?.trim() === 'Editar');
        await new DOMWrapper(editItem as Element).trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(wrapper.emitted('edit')).toBeTruthy();
    });

    it('has an accessible label naming the unit on the trigger button', () => {
        const wrapper = mount(UnitRowActions, { props: { unit: baseUnit } });

        expect(
            wrapper
                .find('button[aria-label="Ações para Unidade Centro"]')
                .exists(),
        ).toBe(true);
    });
});

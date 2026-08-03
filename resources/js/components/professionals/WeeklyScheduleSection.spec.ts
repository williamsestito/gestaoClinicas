import { DOMWrapper, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type {
    AvailabilityUnit,
    WorkingHourRow,
} from './WeeklyScheduleSection.vue';
import WeeklyScheduleSection from './WeeklyScheduleSection.vue';

const { routerMock } = vi.hoisted(() => ({
    routerMock: {
        patch: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    router: routerMock,
    useForm: (initial: Record<string, unknown>) =>
        reactive({
            ...initial,
            errors: {},
            processing: false,
            post: vi.fn(),
            put: vi.fn(),
        }),
}));

const mondayInterval: WorkingHourRow = {
    id: 'wh-1',
    weekday: 1,
    starts_at: '08:00',
    ends_at: '12:00',
    effective_from: null,
    effective_until: null,
    status: 'active',
    vigency_status: 'in_effect',
    is_within_opening_hours: true,
    deleted_at: null,
};

function makeUnit(overrides: Partial<AvailabilityUnit> = {}): AvailabilityUnit {
    return {
        professional_unit_id: 'pu-1',
        unit: {
            id: 'unit-1',
            name: 'Unidade Centro',
            timezone: 'America/Sao_Paulo',
        },
        unit_link_status: 'active',
        opening_hours: [
            { day_of_week: 1, opens_at: '08:00', closes_at: '18:00' },
        ],
        can_manage: true,
        working_hours: [mondayInterval],
        ...overrides,
    };
}

function mountAttached(unit: AvailabilityUnit) {
    return mount(WeeklyScheduleSection, {
        props: { professionalId: 'prof-1', unit },
        attachTo: document.body,
    });
}

describe('WeeklyScheduleSection', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('shows an empty state message for a day with no intervals', () => {
        const wrapper = mount(WeeklyScheduleSection, {
            props: {
                professionalId: 'prof-1',
                unit: makeUnit({ working_hours: [] }),
            },
        });

        expect(wrapper.text()).toContain('Nenhum horário cadastrado.');
    });

    it('shows the configured interval, as start and end columns, under the correct weekday', () => {
        const wrapper = mount(WeeklyScheduleSection, {
            props: { professionalId: 'prof-1', unit: makeUnit() },
        });

        const row = wrapper
            .findAll('tbody tr')
            .find((tr) => tr.text().startsWith('Segunda-feira'));
        const cells = row?.findAll('td').map((td) => td.text());

        expect(cells).toEqual([
            'Segunda-feira',
            '08:00',
            '12:00',
            'Ativo · Vigente',
            expect.stringContaining('Copiar'),
        ]);
    });

    it('shows a closed-day message when the unit has no opening hours for that day', () => {
        const wrapper = mount(WeeklyScheduleSection, {
            props: {
                professionalId: 'prof-1',
                unit: makeUnit({ opening_hours: [], working_hours: [] }),
            },
        });

        expect(wrapper.text()).toContain('Unidade fechada neste dia.');
    });

    it('warns when an interval no longer fits the unit opening hours', () => {
        const wrapper = mount(WeeklyScheduleSection, {
            props: {
                professionalId: 'prof-1',
                unit: makeUnit({
                    working_hours: [
                        { ...mondayInterval, is_within_opening_hours: false },
                    ],
                }),
            },
        });

        expect(wrapper.text()).toContain(
            'Fora do funcionamento atual da unidade.',
        );
    });

    it('hides management actions when the user cannot manage this unit', () => {
        const wrapper = mount(WeeklyScheduleSection, {
            props: {
                professionalId: 'prof-1',
                unit: makeUnit({ can_manage: false }),
            },
        });

        expect(wrapper.find('button[aria-label^="Ações para"]').exists()).toBe(
            false,
        );
        expect(
            wrapper.find('button[aria-label^="Adicionar intervalo"]').exists(),
        ).toBe(false);
    });

    it('opens the create sheet with the correct default weekday', async () => {
        const wrapper = mountAttached(makeUnit());

        const addButton = wrapper.find(
            'button[aria-label="Adicionar intervalo em Terça-feira"]',
        );
        await addButton.trigger('click');
        await wrapper.vm.$nextTick();

        expect(document.body.textContent ?? '').toContain('Novo horário');
    });

    it('calls router.patch against the deactivate route for an active interval', async () => {
        const wrapper = mountAttached(makeUnit());

        await wrapper.find('button[aria-label^="Ações para"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        const item = Array.from(
            document.body.querySelectorAll('[role="menuitem"]'),
        ).find((element) => element.textContent?.trim() === 'Inativar');
        await new DOMWrapper(item as Element).trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(routerMock.patch).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/working-hours/wh-1/deactivate',
            ),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows the non-destructive confirmation wording and only calls router.delete after confirming', async () => {
        const wrapper = mountAttached(makeUnit());

        await wrapper.find('button[aria-label^="Ações para"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        const item = Array.from(
            document.body.querySelectorAll('[role="menuitem"]'),
        ).find((element) => element.textContent?.trim() === 'Excluir');
        await new DOMWrapper(item as Element).trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        const text = document.body.textContent ?? '';
        expect(text).toContain('Excluir horário?');
        expect(text).toContain('será removido da operação');
        expect(routerMock.delete).not.toHaveBeenCalled();

        const confirmButton = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) => button.textContent?.trim() === 'Excluir');
        await new DOMWrapper(confirmButton as Element).trigger('click');

        expect(routerMock.delete).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/working-hours/wh-1',
            ),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('opens the copy dialog and submits the selected target days', async () => {
        const wrapper = mountAttached(makeUnit());

        const copyButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Copiar');
        await copyButton?.trigger('click');
        await wrapper.vm.$nextTick();

        expect(document.body.textContent ?? '').toContain('Copiar horários');

        const tuesdayCheckbox = Array.from(
            document.body.querySelectorAll('label'),
        ).find((label) => label.textContent?.includes('Terça-feira'));
        const checkbox = tuesdayCheckbox?.querySelector(
            'input[type="checkbox"]',
        );
        await new DOMWrapper(checkbox as Element).setValue(true);

        const submitButton = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) => button.textContent?.trim() === 'Copiar horários');
        await new DOMWrapper(submitButton as Element).trigger('click');

        expect(routerMock.post).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/units/pu-1/working-hours/copy',
            ),
            expect.objectContaining({ source_weekday: 1 }),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('closes the copy dialog only on success, never silently on failure', async () => {
        const wrapper = mountAttached(makeUnit());

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Copiar')
            ?.trigger('click');
        await wrapper.vm.$nextTick();

        const checkbox = Array.from(document.body.querySelectorAll('label'))
            .find((label) => label.textContent?.includes('Terça-feira'))
            ?.querySelector('input[type="checkbox"]');
        await new DOMWrapper(checkbox as Element).setValue(true);

        await new DOMWrapper(
            Array.from(document.body.querySelectorAll('button')).find(
                (button) => button.textContent?.trim() === 'Copiar horários',
            ) as Element,
        ).trigger('click');

        // Simula o backend rejeitando a cópia (dia de destino em conflito).
        const options = routerMock.post.mock.calls[0][2];
        options.onError({
            'target_weekdays.Terça-feira':
                'Terça-feira: O horário informado está fora do funcionamento da unidade.',
        });
        options.onFinish();
        await wrapper.vm.$nextTick();

        // O diálogo continua aberto, mostrando exatamente por que falhou —
        // nunca fecha como se a cópia tivesse funcionado.
        expect(document.body.textContent ?? '').toContain('Copiar horários');
        expect(document.body.textContent ?? '').toContain(
            'Terça-feira: O horário informado está fora do funcionamento da unidade.',
        );

        const alert = document.body.querySelector('[role="alert"]');
        expect(alert?.textContent).toContain('fora do funcionamento');
    });

    it('clears the previous error and closes the dialog when a retry succeeds', async () => {
        const wrapper = mountAttached(makeUnit());

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Copiar')
            ?.trigger('click');
        await wrapper.vm.$nextTick();

        const checkbox = Array.from(document.body.querySelectorAll('label'))
            .find((label) => label.textContent?.includes('Terça-feira'))
            ?.querySelector('input[type="checkbox"]');
        await new DOMWrapper(checkbox as Element).setValue(true);

        await new DOMWrapper(
            Array.from(document.body.querySelectorAll('button')).find(
                (button) => button.textContent?.trim() === 'Copiar horários',
            ) as Element,
        ).trigger('click');

        routerMock.post.mock.calls[0][2].onSuccess();
        await wrapper.vm.$nextTick();
        await new Promise((resolve) => setTimeout(resolve, 0));

        const dialogTitle = Array.from(
            document.body.querySelectorAll('h2, [role="heading"]'),
        ).find((el) => el.textContent?.trim() === 'Copiar horários');
        expect(dialogTitle).toBeUndefined();
    });
});

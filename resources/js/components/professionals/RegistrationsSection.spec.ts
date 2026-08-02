import { DOMWrapper, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type { RegistrationRow } from './RegistrationsSection.vue';
import RegistrationsSection from './RegistrationsSection.vue';

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

const crm: RegistrationRow = {
    id: 'reg-1',
    council: 'CRM',
    registration_type: null,
    masked_registration_number: '••••••4321',
    state: 'SP',
    issued_at: '2020-01-01',
    expires_at: null,
    validity_status: 'no_expiration',
    is_primary: true,
    status: 'active',
    deleted_at: null,
};

const coren: RegistrationRow = {
    id: 'reg-2',
    council: 'COREN',
    registration_type: null,
    masked_registration_number: '••••••9876',
    state: 'RJ',
    issued_at: '2020-01-01',
    expires_at: '2026-08-10',
    validity_status: 'expiring_soon',
    is_primary: false,
    status: 'active',
    deleted_at: null,
};

function mountAttached(props: {
    professionalId: string;
    registrations: RegistrationRow[];
    canViewSensitive: boolean;
}) {
    return mount(RegistrationsSection, { props, attachTo: document.body });
}

async function openMenu(wrapper: ReturnType<typeof mountAttached>) {
    await wrapper.find('button[aria-label^="Ações para"]').trigger('click');
    await new Promise((resolve) => setTimeout(resolve, 0));
}

async function clickMenuItem(text: string) {
    const item = Array.from(
        document.body.querySelectorAll('[role="menuitem"]'),
    ).find((element) => element.textContent?.trim() === text);
    await new DOMWrapper(item as Element).trigger('click');
    await new Promise((resolve) => setTimeout(resolve, 0));
}

describe('RegistrationsSection', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
        vi.unstubAllGlobals();
    });

    it('shows an empty state message when there are no registrations', () => {
        const wrapper = mount(RegistrationsSection, {
            props: {
                professionalId: 'prof-1',
                registrations: [],
                canViewSensitive: false,
            },
        });

        expect(wrapper.text()).toContain(
            'Nenhum registro profissional cadastrado ainda.',
        );
    });

    it('always displays the masked number and never the raw registration number', () => {
        const wrapper = mount(RegistrationsSection, {
            props: {
                professionalId: 'prof-1',
                registrations: [crm],
                canViewSensitive: false,
            },
        });

        expect(wrapper.text()).toContain('••••••4321');
    });

    it('shows the validity status label for each registration', () => {
        const wrapper = mount(RegistrationsSection, {
            props: {
                professionalId: 'prof-1',
                registrations: [crm, coren],
                canViewSensitive: false,
            },
        });

        expect(wrapper.text()).toContain('Sem validade informada');
        expect(wrapper.text()).toContain('Próximo do vencimento');
    });

    it('hides the "Ver número completo" action when the user cannot view sensitive data', () => {
        const wrapper = mount(RegistrationsSection, {
            props: {
                professionalId: 'prof-1',
                registrations: [crm],
                canViewSensitive: false,
            },
        });

        expect(wrapper.text()).not.toContain('Ver número completo');
    });

    it('reveals the full registration number via fetch when authorized and clicked', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => ({ registration_number: '123456-SP' }),
            }),
        );

        const wrapper = mount(RegistrationsSection, {
            props: {
                professionalId: 'prof-1',
                registrations: [crm],
                canViewSensitive: true,
            },
        });

        await wrapper.find('button:not([aria-label])').trigger('click');
        await wrapper.vm.$nextTick();
        await new Promise((resolve) => setTimeout(resolve, 0));
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('123456-SP');
        expect(wrapper.text()).not.toContain('Ver número completo');
    });

    it('calls router.patch against the primary route when "Definir como principal" is selected', async () => {
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            registrations: [coren],
            canViewSensitive: false,
        });

        await openMenu(wrapper);
        await clickMenuItem('Definir como principal');

        expect(routerMock.patch).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/registrations/reg-2/primary',
            ),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('calls router.patch against the deactivate route for an active registration', async () => {
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            registrations: [coren],
            canViewSensitive: false,
        });

        await openMenu(wrapper);
        await clickMenuItem('Inativar');

        expect(routerMock.patch).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/registrations/reg-2/deactivate',
            ),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows the non-destructive confirmation wording and only calls router.delete after confirming', async () => {
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            registrations: [coren],
            canViewSensitive: false,
        });

        await openMenu(wrapper);
        await clickMenuItem('Excluir');

        const text = document.body.textContent ?? '';
        expect(text).toContain('Excluir registro profissional?');
        expect(text).toContain('será removido da operação');
        expect(text).toContain('histórico será preservado');
        expect(routerMock.delete).not.toHaveBeenCalled();

        const confirmButton = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) => button.textContent?.trim() === 'Excluir');
        await new DOMWrapper(confirmButton as Element).trigger('click');

        expect(routerMock.delete).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/registrations/reg-2',
            ),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows only "Restaurar" for a soft-deleted registration and calls router.post on restore', async () => {
        const deleted: RegistrationRow = {
            ...coren,
            deleted_at: '2026-07-19T12:00:00Z',
        };
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            registrations: [deleted],
            canViewSensitive: false,
        });

        await openMenu(wrapper);
        const text = document.body.textContent ?? '';
        expect(text).toContain('Restaurar');
        expect(text).not.toContain('Excluir');

        await clickMenuItem('Restaurar');

        expect(routerMock.post).toHaveBeenCalledWith(
            expect.stringContaining(
                '/settings/professionals/prof-1/registrations/reg-2/restore',
            ),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('opens the create sheet with the registration form when "Novo registro" is clicked', async () => {
        const wrapper = mountAttached({
            professionalId: 'prof-1',
            registrations: [],
            canViewSensitive: false,
        });

        const newButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Novo registro'));
        await newButton?.trigger('click');
        await wrapper.vm.$nextTick();

        // O conteúdo do Sheet é renderizado via Teleport para document.body.
        expect(document.body.textContent ?? '').toContain(
            'Novo registro profissional',
        );
    });
});

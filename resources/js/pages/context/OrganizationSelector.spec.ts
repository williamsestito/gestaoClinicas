import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import type { Organization } from '@/types/organization';
import OrganizationSelector from './OrganizationSelector.vue';

const { formPutMock, pageProps } = vi.hoisted(() => ({
    formPutMock: vi.fn(),
    pageProps: {
        tenant: { isPlatformAdmin: false } as { isPlatformAdmin: boolean },
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    useForm: (initial: Record<string, unknown>) => ({
        ...initial,
        processing: false,
        put: formPutMock,
    }),
    usePage: () => ({ props: pageProps }),
}));

function makeOrganization(id: string, name: string): Organization {
    return {
        id,
        name,
        slug: name.toLowerCase().replace(/\s+/g, '-'),
        status: 'active',
        default_timezone: 'America/Sao_Paulo',
        default_currency: 'BRL',
        locale: 'pt_BR',
        primary_color: null,
        secondary_color: null,
        allow_appointment_overlap: false,
    };
}

const organizations: Organization[] = [
    makeOrganization('org-1', 'Clínica A'),
    makeOrganization('org-2', 'Clínica B'),
];

describe('context/OrganizationSelector', () => {
    it('shows generic "Selecionar" wording for a regular multi-organization user', () => {
        pageProps.tenant.isPlatformAdmin = false;

        const wrapper = mount(OrganizationSelector, {
            props: { organizations },
        });

        expect(wrapper.text()).toContain('Escolha uma organização');
        expect(wrapper.text()).not.toContain('gerenciar');
        expect(
            wrapper.findAll('button').some((b) => b.text() === 'Selecionar'),
        ).toBe(true);
    });

    it('shows "Gerenciar esta organização" wording for a platform admin', () => {
        pageProps.tenant.isPlatformAdmin = true;

        const wrapper = mount(OrganizationSelector, {
            props: { organizations },
        });

        expect(wrapper.text()).toContain(
            'Escolha uma organização para gerenciar',
        );
        expect(
            wrapper
                .findAll('button')
                .some((b) => b.text() === 'Gerenciar esta organização'),
        ).toBe(true);
    });

    it('submits the chosen organization id when a card is clicked', async () => {
        pageProps.tenant.isPlatformAdmin = true;

        const wrapper = mount(OrganizationSelector, {
            props: { organizations },
        });

        await wrapper.findAll('[class*="cursor-pointer"]')[1].trigger('click');

        expect(formPutMock).toHaveBeenCalledWith(expect.any(String));
    });
});

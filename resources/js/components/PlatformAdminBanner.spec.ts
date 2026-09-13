import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import PlatformAdminBanner from './PlatformAdminBanner.vue';

const { routerDeleteMock, pageProps } = vi.hoisted(() => ({
    routerDeleteMock: vi.fn(),
    pageProps: {
        tenant: null as {
            isPlatformAdmin: boolean;
            organization: { name: string } | null;
        } | null,
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { delete: routerDeleteMock },
    usePage: () => ({ props: pageProps }),
}));

describe('PlatformAdminBanner', () => {
    it('renders nothing when the user is not a platform admin', () => {
        pageProps.tenant = {
            isPlatformAdmin: false,
            organization: { name: 'Clínica Exemplo' },
        };

        const wrapper = mount(PlatformAdminBanner);

        expect(wrapper.text()).toBe('');
    });

    it('renders nothing when a platform admin has no active organization', () => {
        pageProps.tenant = { isPlatformAdmin: true, organization: null };

        const wrapper = mount(PlatformAdminBanner);

        expect(wrapper.text()).toBe('');
    });

    it('shows the organization name and an exit action when a platform admin is managing an organization', () => {
        pageProps.tenant = {
            isPlatformAdmin: true,
            organization: { name: 'Clínica Exemplo' },
        };

        const wrapper = mount(PlatformAdminBanner);

        expect(wrapper.text()).toContain('Clínica Exemplo');
        expect(wrapper.text()).toContain('Superadmin');
        expect(wrapper.text()).toContain('Sair do modo de gestão');
    });

    it('asks the server to leave management mode when the exit button is clicked', async () => {
        pageProps.tenant = {
            isPlatformAdmin: true,
            organization: { name: 'Clínica Exemplo' },
        };

        const wrapper = mount(PlatformAdminBanner);

        await wrapper.find('button').trigger('click');

        expect(routerDeleteMock).toHaveBeenCalledWith(expect.any(String));
    });
});

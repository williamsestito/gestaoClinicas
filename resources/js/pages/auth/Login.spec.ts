import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Login from './Login.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    Form: {
        template: '<form><slot :errors="{}" :processing="false" /></form>',
    },
}));

vi.mock('@/components/PasskeyVerify.vue', () => ({
    default: { template: '<div />' },
}));

function setUrl(search: string) {
    window.history.pushState({}, '', `/login${search}`);
}

describe('auth/Login', () => {
    afterEach(() => {
        setUrl('');
    });

    it('sends "Cadastre-se" to the generic account chooser when there is no patient context', () => {
        setUrl('');
        const wrapper = mount(Login, {
            props: { canResetPassword: true },
        });

        const link = wrapper
            .findAll('a')
            .find((a) => a.text() === 'Cadastre-se');
        expect(link?.attributes('href')).toBe('/register');
    });

    it('sends "Cadastre-se" straight to the patient portal registration, prefilled, when it carries pré-agendamento data', () => {
        // Achado real: quem clicava em "Acessar o portal do paciente" na
        // landing pública ainda não tinha conta — o login falhava e não
        // havia caminho direto de volta ao cadastro certo a partir daqui.
        setUrl('?name=Ana+Souza&phone=47999990000&document=12345678900');
        const wrapper = mount(Login, {
            props: { canResetPassword: true },
        });

        const link = wrapper
            .findAll('a')
            .find((a) => a.text() === 'Cadastre-se');
        const href = link?.attributes('href') ?? '';

        expect(href).toContain('/portal/registrar');
        expect(href).toContain('name=Ana+Souza');
        expect(href).toContain('document=12345678900');
    });

    it('ignores unrelated query strings and still falls back to the generic chooser', () => {
        setUrl('?utm_source=google');
        const wrapper = mount(Login, {
            props: { canResetPassword: true },
        });

        const link = wrapper
            .findAll('a')
            .find((a) => a.text() === 'Cadastre-se');
        expect(link?.attributes('href')).toBe('/register');
    });
});

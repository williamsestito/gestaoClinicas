import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Dashboard from './Dashboard.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { props: ['href'], template: '<a><slot /></a>' },
}));

const patients = [
    {
        id: 'patient-1',
        name: 'Ana Souza',
        birth_date: '1990-05-10',
        role: 'self' as const,
        role_label: 'Titular',
        photo_url: null,
        next_appointment: {
            starts_at: '2026-09-10T12:00:00Z',
            status_label: 'Confirmado',
        },
        last_appointment: {
            starts_at: '2026-07-01T12:00:00Z',
            status_label: 'Concluído',
        },
        pending_requests_count: 2,
    },
    {
        id: 'patient-2',
        name: 'Bruno Souza',
        birth_date: '2015-02-01',
        role: 'dependent' as const,
        role_label: 'Dependente',
        photo_url: 'https://example.test/foto.jpg',
        next_appointment: null,
        last_appointment: null,
        pending_requests_count: 0,
    },
];

describe('patient-portal/Dashboard', () => {
    it('lists every linked patient with name, birth date and role', () => {
        const wrapper = mount(Dashboard, {
            props: { patients, clinicContact: null },
        });

        const text = wrapper.text();
        expect(text).toContain('Ana Souza');
        expect(text).toContain('10/05/1990');
        expect(text).toContain('Titular');
        expect(text).toContain('Bruno Souza');
        expect(text).toContain('Dependente');
    });

    it('shows initials as the avatar fallback when there is no photo', () => {
        const wrapper = mount(Dashboard, {
            props: { patients, clinicContact: null },
        });

        expect(wrapper.text()).toContain('AS');
    });

    it('renders the photo as the avatar image when one is on file', () => {
        const wrapper = mount(Dashboard, {
            props: { patients, clinicContact: null },
        });

        const img = wrapper.find('img[alt="Bruno Souza"]');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('https://example.test/foto.jpg');
    });

    it('shows the next and last appointment when they exist', () => {
        const wrapper = mount(Dashboard, {
            props: { patients, clinicContact: null },
        });

        const text = wrapper.text();
        expect(text).toContain('Confirmado');
        expect(text).toContain('01/07/2026');
    });

    it('shows a placeholder when there is no next or last appointment', () => {
        const wrapper = mount(Dashboard, {
            props: { patients, clinicContact: null },
        });

        const text = wrapper.text();
        expect(text).toContain('Nenhuma agendada');
        expect(text).toContain('Ainda sem histórico');
    });

    it('links to the pending requests count only when there are pending requests', () => {
        const wrapper = mount(Dashboard, {
            props: { patients, clinicContact: null },
        });

        expect(wrapper.text()).toContain(
            '2 pré-agendamento(s) aguardando confirmação',
        );
    });

    it('shows a "fale conosco" card with WhatsApp and phone links when contact info exists', () => {
        const wrapper = mount(Dashboard, {
            props: {
                patients,
                clinicContact: {
                    name: 'Clinica Teste',
                    phone: '(47) 3333-4444',
                    whatsapp: '(47) 99999-0000',
                },
            },
        });

        const text = wrapper.text();
        expect(text).toContain('Fale conosco');
        expect(text).toContain('Clinica Teste');

        const links = wrapper.findAll('a').map((a) => a.attributes('href'));
        expect(links).toContain('https://wa.me/5547999990000');
        expect(links).toContain('tel:4733334444');
    });

    it('does not show the "fale conosco" card when there is no contact info', () => {
        const wrapper = mount(Dashboard, {
            props: { patients, clinicContact: null },
        });

        expect(wrapper.text()).not.toContain('Fale conosco');
    });
});

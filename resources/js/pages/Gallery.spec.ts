import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Gallery from './Gallery.vue';

const { routerMock } = vi.hoisted(() => ({
    routerMock: { visit: vi.fn() },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>', props: ['href'] },
    router: routerMock,
}));

function makeItems(count: number) {
    return {
        data: Array.from({ length: count }, (_, i) => ({
            id: i + 1,
            image_url: `/storage/gallery-${i + 1}.jpg`,
            caption: `Legenda ${i + 1}`,
            alt_text: null,
        })),
        current_page: 1,
        last_page: count > 24 ? 2 : 1,
        links: [
            { url: null, label: '&laquo; Previous', active: false },
            { url: '/galeria?page=1', label: '1', active: true },
            { url: '/galeria?page=2', label: '2', active: false },
            { url: '/galeria?page=2', label: 'Next &raquo;', active: false },
        ],
    };
}

describe('Gallery', () => {
    it('shows an empty message when there are no items', () => {
        const wrapper = mount(Gallery, {
            props: { siteTitle: 'Clínica Essenza', logoUrl: null, items: null },
        });

        expect(wrapper.text()).toContain(
            'Nenhuma imagem publicada no momento.',
        );
    });

    it('renders every item with its caption', () => {
        const wrapper = mount(Gallery, {
            props: {
                siteTitle: 'Clínica Essenza',
                logoUrl: null,
                items: makeItems(4),
            },
        });

        expect(wrapper.findAll('img[loading="lazy"]')).toHaveLength(4);
        expect(wrapper.text()).toContain('Legenda 1');
        expect(wrapper.text()).toContain('Legenda 4');
    });

    it('has a link back to the home page', () => {
        const wrapper = mount(Gallery, {
            props: {
                siteTitle: 'Clínica Essenza',
                logoUrl: null,
                items: makeItems(4),
            },
        });

        expect(wrapper.text()).toContain('Voltar para o início');
    });

    it('shows pagination controls when there is more than one page', () => {
        const wrapper = mount(Gallery, {
            props: {
                siteTitle: 'Clínica Essenza',
                logoUrl: null,
                items: makeItems(30),
            },
        });

        expect(
            wrapper.find('nav[aria-label="Paginação da galeria"]').exists(),
        ).toBe(true);
    });

    it('does not show pagination controls for a single page', () => {
        const wrapper = mount(Gallery, {
            props: {
                siteTitle: 'Clínica Essenza',
                logoUrl: null,
                items: makeItems(4),
            },
        });

        expect(
            wrapper.find('nav[aria-label="Paginação da galeria"]').exists(),
        ).toBe(false);
    });

    it('opens the enlarged image lightbox when a thumbnail is clicked', async () => {
        const wrapper = mount(Gallery, {
            props: {
                siteTitle: 'Clínica Essenza',
                logoUrl: null,
                items: makeItems(4),
            },
            attachTo: document.body,
        });

        await wrapper.findAll('button')[1]!.trigger('click');
        await wrapper.vm.$nextTick();

        expect(
            document.body.querySelector('img[class*="max-h"]'),
        ).not.toBeNull();

        wrapper.unmount();
    });
});

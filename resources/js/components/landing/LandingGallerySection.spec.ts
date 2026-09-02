import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import type { PublicGalleryItem } from '@/types/site';
import LandingGallerySection from './LandingGallerySection.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>', props: ['href'] },
}));

function makeItems(count: number): PublicGalleryItem[] {
    return Array.from({ length: count }, (_, i) => ({
        id: i + 1,
        image_url: `/storage/gallery-${i + 1}.jpg`,
        caption: `Legenda ${i + 1}`,
        alt_text: null,
        category: null,
        is_cover: false,
    }));
}

describe('LandingGallerySection', () => {
    it('does not render the section when there are no items', () => {
        const wrapper = mount(LandingGallerySection, { props: { items: [] } });

        expect(wrapper.find('section').exists()).toBe(false);
    });

    it('renders a limited preview of images as a horizontally scrollable carousel', () => {
        const wrapper = mount(LandingGallerySection, {
            props: { items: makeItems(20) },
        });

        const track = wrapper.find('[role="region"]');
        expect(track.exists()).toBe(true);
        expect(track.findAll('button').length).toBe(12);
    });

    it('shows a "Ver todas" link only when there are more images than the preview limit', () => {
        const few = mount(LandingGallerySection, {
            props: { items: makeItems(5) },
        });
        expect(few.text()).not.toContain('Ver todas');

        const many = mount(LandingGallerySection, {
            props: { items: makeItems(20) },
        });
        expect(many.text()).toContain('Ver todas');
    });

    it('shows the caption under each preview image', () => {
        const wrapper = mount(LandingGallerySection, {
            props: { items: makeItems(3) },
        });

        expect(wrapper.text()).toContain('Legenda 1');
        expect(wrapper.text()).toContain('Legenda 2');
    });

    it('opens the lightbox with the clicked image and its caption', async () => {
        const wrapper = mount(LandingGallerySection, {
            props: { items: makeItems(3) },
            attachTo: document.body,
        });

        await wrapper.findAll('[role="region"] button')[1]!.trigger('click');
        await wrapper.vm.$nextTick();

        expect(document.body.textContent).toContain('Legenda 2');

        wrapper.unmount();
    });
});

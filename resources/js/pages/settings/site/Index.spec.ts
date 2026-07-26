import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import ImageUploadField from '@/components/ImageUploadField.vue';
import Index from './Index.vue';

const { routerMock } = vi.hoisted(() => ({
    routerMock: {
        patch: vi.fn(),
        put: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    router: routerMock,
    useForm: (initial: Record<string, unknown>) =>
        reactive({
            ...initial,
            errors: {},
            processing: false,
            transform: () => ({ post: vi.fn() }),
            post: vi.fn(),
            put: vi.fn(),
        }),
}));

function baseSite() {
    return {
        title: 'Clínica Exemplo',
        description: null,
        hero_image_url: null as string | null,
        hero_image_mobile_url: null as string | null,
        logo_url: null,
        favicon_url: null,
        primary_color: null,
        secondary_color: null,
        cta_text: null,
        cta_url: null,
        cta_secondary_text: null,
        cta_secondary_url: null,
        about_text: null,
        facebook_url: null,
        instagram_url: null,
        linkedin_url: null,
        footer_text: null,
        is_published: false,
    };
}

describe('settings/site/Index — banner', () => {
    it('does not show a "remove banner" action when there is no banner yet', () => {
        const wrapper = mount(Index, {
            props: { site: baseSite(), contact: null },
        });

        expect(wrapper.text()).not.toContain('Remover banner desktop atual');
    });

    it('shows the current banner and a "remove banner" action when one exists', () => {
        const wrapper = mount(Index, {
            props: {
                site: {
                    ...baseSite(),
                    hero_image_url: 'https://example.test/storage/hero.webp',
                },
                contact: null,
            },
        });

        expect(
            wrapper.findComponent(ImageUploadField).props('currentUrl'),
        ).toBe('https://example.test/storage/hero.webp');
        expect(wrapper.text()).toContain('Remover banner desktop atual');
    });

    it('hides the "remove banner" action while a new file is staged for upload', async () => {
        const wrapper = mount(Index, {
            props: {
                site: {
                    ...baseSite(),
                    hero_image_url: 'https://example.test/storage/hero.webp',
                },
                contact: null,
            },
        });

        expect(wrapper.text()).toContain('Remover banner desktop atual');

        await wrapper
            .findComponent(ImageUploadField)
            .vm.$emit(
                'update:modelValue',
                new File(['x'], 'novo.webp', { type: 'image/webp' }),
            );

        expect(wrapper.text()).not.toContain('Remover banner desktop atual');
    });

    it('asks for confirmation and deletes the banner via DELETE settings/site/hero-image', async () => {
        const wrapper = mount(Index, {
            props: {
                site: {
                    ...baseSite(),
                    hero_image_url: 'https://example.test/storage/hero.webp',
                },
                contact: null,
            },
            attachTo: document.body,
        });

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Remover banner desktop atual')
            ?.trigger('click');
        await wrapper.vm.$nextTick();

        const dialogText = document.body.textContent ?? '';
        expect(dialogText).toContain('Remover banner desktop?');

        const confirmButton = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) => button.textContent?.trim() === 'Remover');
        confirmButton?.dispatchEvent(new Event('click'));
        await wrapper.vm.$nextTick();

        expect(routerMock.delete).toHaveBeenCalledWith(
            '/settings/site/hero-image',
            expect.objectContaining({ preserveScroll: true }),
        );

        wrapper.unmount();
    });
});

describe.each([
    {
        asset: 'hero_image_mobile' as const,
        urlKey: 'hero_image_mobile_url' as const,
        label: 'Remover banner mobile atual',
        dialogTitle: 'Remover banner mobile?',
        endpoint: '/settings/site/hero-image-mobile',
    },
    {
        asset: 'logo' as const,
        urlKey: 'logo_url' as const,
        label: 'Remover logotipo atual',
        dialogTitle: 'Remover logotipo?',
        endpoint: '/settings/site/logo',
    },
    {
        asset: 'favicon' as const,
        urlKey: 'favicon_url' as const,
        label: 'Remover favicon atual',
        dialogTitle: 'Remover favicon?',
        endpoint: '/settings/site/favicon',
    },
])('settings/site/Index — $asset', ({ urlKey, label, dialogTitle, endpoint }) => {
    it(`does not show a "${label}" action when there is none yet`, () => {
        const wrapper = mount(Index, {
            props: { site: baseSite(), contact: null },
        });

        expect(wrapper.text()).not.toContain(label);
    });

    it(`shows the "${label}" action when one exists`, () => {
        const wrapper = mount(Index, {
            props: {
                site: {
                    ...baseSite(),
                    [urlKey]: 'https://example.test/storage/asset.png',
                },
                contact: null,
            },
        });

        expect(wrapper.text()).toContain(label);
    });

    it('asks for confirmation and deletes it via the matching DELETE endpoint', async () => {
        const wrapper = mount(Index, {
            props: {
                site: {
                    ...baseSite(),
                    [urlKey]: 'https://example.test/storage/asset.png',
                },
                contact: null,
            },
            attachTo: document.body,
        });

        await wrapper
            .findAll('button')
            .find((button) => button.text() === label)
            ?.trigger('click');
        await wrapper.vm.$nextTick();

        expect(document.body.textContent ?? '').toContain(dialogTitle);

        const confirmButton = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) => button.textContent?.trim() === 'Remover');
        confirmButton?.dispatchEvent(new Event('click'));
        await wrapper.vm.$nextTick();

        expect(routerMock.delete).toHaveBeenCalledWith(
            endpoint,
            expect.objectContaining({ preserveScroll: true }),
        );

        wrapper.unmount();
    });
});

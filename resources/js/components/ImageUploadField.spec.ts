import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ImageUploadField from './ImageUploadField.vue';

function fakeImageFile(name = 'banner.webp') {
    return new File(['fake-image-content'], name, { type: 'image/webp' });
}

describe('ImageUploadField', () => {
    beforeEach(() => {
        URL.createObjectURL = vi.fn(() => 'blob:preview-url');
        URL.revokeObjectURL = vi.fn();
    });

    it('shows the current image when there is no new selection', () => {
        const wrapper = mount(ImageUploadField, {
            props: {
                id: 'hero_image',
                label: 'Banner principal',
                currentUrl: 'https://example.test/storage/hero.webp',
            },
        });

        const img = wrapper.find('img');
        expect(img.attributes('src')).toBe(
            'https://example.test/storage/hero.webp',
        );
    });

    it('shows no image when there is neither a current image nor a selection', () => {
        const wrapper = mount(ImageUploadField, {
            props: { id: 'hero_image', label: 'Banner principal' },
        });

        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('previews the selected file and updates the model', async () => {
        const wrapper = mount(ImageUploadField, {
            props: { id: 'hero_image', label: 'Banner principal' },
        });

        const file = fakeImageFile();
        const input = wrapper.find('input[type="file"]')
            .element as HTMLInputElement;
        Object.defineProperty(input, 'files', {
            value: [file],
            configurable: true,
        });
        await wrapper.find('input[type="file"]').trigger('change');

        expect(URL.createObjectURL).toHaveBeenCalledWith(file);
        expect(wrapper.find('img').attributes('src')).toBe('blob:preview-url');
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([file]);
    });

    it('restores the current image and clears the model when the selection is cancelled', async () => {
        const wrapper = mount(ImageUploadField, {
            props: {
                id: 'hero_image',
                label: 'Banner principal',
                currentUrl: 'https://example.test/storage/hero.webp',
            },
        });

        const file = fakeImageFile();
        const input = wrapper.find('input[type="file"]')
            .element as HTMLInputElement;
        Object.defineProperty(input, 'files', {
            value: [file],
            configurable: true,
        });
        await wrapper.find('input[type="file"]').trigger('change');

        expect(wrapper.find('img').attributes('src')).toBe('blob:preview-url');

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Remover seleção')
            ?.trigger('click');

        expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:preview-url');
        expect(wrapper.find('img').attributes('src')).toBe(
            'https://example.test/storage/hero.webp',
        );
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null]);
    });

    it('labels the trigger button "Trocar imagem" when there is already a current image', () => {
        const wrapper = mount(ImageUploadField, {
            props: {
                id: 'hero_image',
                label: 'Banner principal',
                currentUrl: 'https://example.test/storage/hero.webp',
            },
        });

        expect(
            wrapper
                .findAll('button')
                .some((button) => button.text().includes('Trocar imagem')),
        ).toBe(true);
    });

    it('opens the file picker when the upload button is clicked, and hides the native input', () => {
        const wrapper = mount(ImageUploadField, {
            props: { id: 'hero_image', label: 'Banner principal' },
        });

        const input = wrapper.find('input[type="file"]')
            .element as HTMLInputElement;
        const clickSpy = vi.spyOn(input, 'click');

        expect(input.className).toContain('hidden');

        const trigger = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Selecionar imagem'));
        trigger?.trigger('click');

        expect(clickSpy).toHaveBeenCalledOnce();
    });

    it('revokes the previous preview url when a second file is selected', async () => {
        const wrapper = mount(ImageUploadField, {
            props: { id: 'hero_image', label: 'Banner principal' },
        });

        const input = wrapper.find('input[type="file"]')
            .element as HTMLInputElement;

        Object.defineProperty(input, 'files', {
            value: [fakeImageFile()],
            configurable: true,
        });
        await wrapper.find('input[type="file"]').trigger('change');

        Object.defineProperty(input, 'files', {
            value: [fakeImageFile('other.png')],
            configurable: true,
        });
        await wrapper.find('input[type="file"]').trigger('change');

        expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:preview-url');
    });
});

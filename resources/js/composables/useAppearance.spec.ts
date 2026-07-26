import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { defineComponent, h } from 'vue';
import { initializeTheme, useAppearance } from './useAppearance';

function stubMatchMedia(prefersDark = false) {
    const listeners = new Set<() => void>();

    window.matchMedia = ((query: string) => ({
        matches: prefersDark,
        media: query,
        onchange: null,
        addEventListener: (_event: string, listener: () => void) => {
            listeners.add(listener);
        },
        removeEventListener: (_event: string, listener: () => void) => {
            listeners.delete(listener);
        },
        addListener: () => {},
        removeListener: () => {},
        dispatchEvent: () => false,
    })) as unknown as typeof window.matchMedia;

    return {
        triggerChange: () => listeners.forEach((listener) => listener()),
    };
}

function mountUseAppearance() {
    let result!: ReturnType<typeof useAppearance>;

    const wrapper = mount(
        defineComponent({
            setup() {
                result = useAppearance();

                return () => h('div');
            },
        }),
    );

    return { wrapper, ...result };
}

describe('initializeTheme', () => {
    beforeEach(() => {
        localStorage.clear();
        document.documentElement.classList.remove('dark');

        // jsdom doesn't implement matchMedia — stub it so
        // initializeTheme's OS-theme listener setup doesn't throw.
        stubMatchMedia();
    });

    it('defaults to light when there is no saved preference, regardless of the OS theme', () => {
        initializeTheme();

        expect(document.documentElement.classList.contains('dark')).toBe(
            false,
        );
    });

    it('applies a previously saved dark preference', () => {
        localStorage.setItem('appearance', 'dark');

        initializeTheme();

        expect(document.documentElement.classList.contains('dark')).toBe(
            true,
        );
    });

    it('applies a previously saved light preference', () => {
        localStorage.setItem('appearance', 'light');

        initializeTheme();

        expect(document.documentElement.classList.contains('dark')).toBe(
            false,
        );
    });

    it('ignores an invalid stored value and falls back to light', () => {
        localStorage.setItem('appearance', 'purple');

        initializeTheme();

        expect(document.documentElement.classList.contains('dark')).toBe(
            false,
        );
    });

    it('only reacts to an OS theme change when the stored preference is "system"', () => {
        const media = stubMatchMedia(false);
        localStorage.setItem('appearance', 'light');
        initializeTheme();

        media.triggerChange();
        expect(document.documentElement.classList.contains('dark')).toBe(
            false,
        );

        localStorage.setItem('appearance', 'system');
        media.triggerChange();
        expect(document.documentElement.classList.contains('dark')).toBe(
            false,
        );
    });
});

describe('useAppearance', () => {
    beforeEach(() => {
        // `appearance` is a module-level singleton ref shared by every
        // useAppearance() call, so it leaks across tests unless explicitly
        // reset here before each one sets up its own scenario.
        localStorage.setItem('appearance', 'light');
        document.cookie = 'appearance=;path=/;max-age=0';
        document.documentElement.classList.remove('dark');
        stubMatchMedia();
        mountUseAppearance();
        localStorage.clear();
    });

    it('defaults to light when there is no saved preference', () => {
        const { appearance, resolvedAppearance } = mountUseAppearance();

        expect(appearance.value).toBe('light');
        expect(resolvedAppearance.value).toBe('light');
    });

    it('restores a previously saved preference on mount', () => {
        localStorage.setItem('appearance', 'dark');

        const { appearance } = mountUseAppearance();

        expect(appearance.value).toBe('dark');
    });

    it('ignores an invalid stored value and keeps the light default', () => {
        localStorage.setItem('appearance', 'purple');

        const { appearance } = mountUseAppearance();

        expect(appearance.value).toBe('light');
    });

    it('applies dark mode, persists to localStorage and cookie when updated to dark', () => {
        const { updateAppearance } = mountUseAppearance();

        updateAppearance('dark');

        expect(document.documentElement.classList.contains('dark')).toBe(
            true,
        );
        expect(localStorage.getItem('appearance')).toBe('dark');
        expect(document.cookie).toContain('appearance=dark');
    });

    it('removes the dark class and persists light when updated to light', () => {
        const { updateAppearance } = mountUseAppearance();

        updateAppearance('dark');
        updateAppearance('light');

        expect(document.documentElement.classList.contains('dark')).toBe(
            false,
        );
        expect(localStorage.getItem('appearance')).toBe('light');
        expect(document.cookie).toContain('appearance=light');
    });

    it('resolves "system" against the current OS preference', () => {
        stubMatchMedia(true);
        const { updateAppearance, resolvedAppearance } = mountUseAppearance();

        updateAppearance('system');

        expect(resolvedAppearance.value).toBe('dark');
    });
});

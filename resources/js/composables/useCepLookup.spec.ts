import { afterEach, describe, expect, it, vi } from 'vitest';
import { useCepLookup } from './useCepLookup';

const successResponse = {
    postal_code: '01310100',
    street: 'Avenida Paulista',
    neighborhood: 'Bela Vista',
    city: 'São Paulo',
    state: 'SP',
    source: 'awesomeapi',
};

describe('useCepLookup', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('returns null and does not call fetch for an incomplete CEP', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        const { lookup, status } = useCepLookup();
        const result = await lookup('0131010');

        expect(result).toBeNull();
        expect(status.value).toBe('idle');
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('sets status to "success" and returns the normalized result on a hit', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => successResponse,
            }),
        );

        const { lookup, status } = useCepLookup();
        const result = await lookup('01310-100');

        expect(status.value).toBe('success');
        expect(result?.source).toBe('awesomeapi');
        expect(result?.city).toBe('São Paulo');
    });

    it('sets status to "not-found" on a 404 response', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({ ok: false, status: 404 }),
        );

        const { lookup, status } = useCepLookup();
        const result = await lookup('00000000');

        expect(result).toBeNull();
        expect(status.value).toBe('not-found');
    });

    it('sets status to "error" on a non-404 failure, without throwing', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({ ok: false, status: 500 }),
        );

        const { lookup, status } = useCepLookup();
        const result = await lookup('01310100');

        expect(result).toBeNull();
        expect(status.value).toBe('error');
    });

    it('sets status to "error" and never throws when fetch rejects', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockRejectedValue(new Error('network down')),
        );

        const { lookup, status } = useCepLookup();

        await expect(lookup('01310100')).resolves.toBeNull();
        expect(status.value).toBe('error');
    });

    it('does not call fetch again for the same CEP unless forced', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => successResponse,
        });
        vi.stubGlobal('fetch', fetchMock);

        const { lookup } = useCepLookup();

        await lookup('01310100');
        await lookup('01310100');
        expect(fetchMock).toHaveBeenCalledTimes(1);

        await lookup('01310100', { force: true });
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('calls fetch again when the CEP changes', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => successResponse,
        });
        vi.stubGlobal('fetch', fetchMock);

        const { lookup } = useCepLookup();

        await lookup('01310100');
        await lookup('04567000');

        expect(fetchMock).toHaveBeenCalledTimes(2);
    });
});

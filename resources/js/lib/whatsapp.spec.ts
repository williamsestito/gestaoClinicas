import { describe, expect, it } from 'vitest';
import { buildWhatsAppUrl } from './whatsapp';

describe('buildWhatsAppUrl', () => {
    it('returns null when there is no phone number', () => {
        expect(buildWhatsAppUrl(null)).toBeNull();
        expect(buildWhatsAppUrl(undefined)).toBeNull();
        expect(buildWhatsAppUrl('')).toBeNull();
    });

    it('prefixes the BR country code when the number does not include it', () => {
        expect(buildWhatsAppUrl('(47) 99999-8888')).toBe(
            'https://wa.me/5547999998888',
        );
    });

    it('does not duplicate the country code when the number already includes it', () => {
        expect(buildWhatsAppUrl('+55 47 99999-8888')).toBe(
            'https://wa.me/5547999998888',
        );
    });

    it('strips all non-digit characters', () => {
        expect(buildWhatsAppUrl('47.99999-8888')).toBe(
            'https://wa.me/5547999998888',
        );
    });
});

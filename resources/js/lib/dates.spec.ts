import { describe, expect, it } from 'vitest';
import { isPastDate, todayDateString } from './dates';

describe('isPastDate', () => {
    it('returns false for today', () => {
        expect(isPastDate(todayDateString())).toBe(false);
    });

    it('returns false for a future date', () => {
        expect(isPastDate('2999-01-01')).toBe(false);
    });

    it('returns true for a past date', () => {
        expect(isPastDate('2000-01-01')).toBe(true);
    });
});

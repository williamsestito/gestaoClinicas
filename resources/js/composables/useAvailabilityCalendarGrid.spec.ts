import { describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { useAvailabilityCalendarGrid } from './useAvailabilityCalendarGrid';

describe('useAvailabilityCalendarGrid', () => {
    it('pads leading blanks for the first day of the month', () => {
        // 2026-08-01 é sábado (getDay() === 6) → 6 células em branco.
        const dates = ref([
            { date: '2026-08-01', is_available: false },
            { date: '2026-08-02', is_available: false },
        ]);
        const currentMonth = ref('2026-08');
        const { calendarCells } = useAvailabilityCalendarGrid(
            dates,
            currentMonth,
        );

        const blanks = calendarCells.value.filter((cell) => cell === null);
        expect(blanks).toHaveLength(6);
    });

    it('marks a day unavailable when the backend says so', () => {
        const dates = ref([{ date: '2026-08-03', is_available: false }]);
        const currentMonth = ref('2026-08');
        const { calendarCells } = useAvailabilityCalendarGrid(
            dates,
            currentMonth,
        );

        expect(calendarCells.value.at(-1)?.isAvailable).toBe(false);
    });

    it('marks a past day unavailable even when the backend marks it as available', () => {
        vi.setSystemTime(new Date('2026-08-02T12:00:00Z'));
        const dates = ref([{ date: '2026-08-01', is_available: true }]);
        const currentMonth = ref('2026-08');
        const { calendarCells } = useAvailabilityCalendarGrid(
            dates,
            currentMonth,
        );

        expect(calendarCells.value.at(-1)?.isAvailable).toBe(false);
        vi.useRealTimers();
    });

    it('formats the month label in Portuguese', () => {
        const dates = ref([]);
        const currentMonth = ref('2026-08');
        const { monthLabel } = useAvailabilityCalendarGrid(dates, currentMonth);

        expect(monthLabel.value).toBe('agosto de 2026');
    });

    it('shiftMonth computes the previous and next month strings', () => {
        const dates = ref([]);
        const currentMonth = ref('2026-08');
        const { shiftMonth } = useAvailabilityCalendarGrid(dates, currentMonth);

        expect(shiftMonth(-1)).toBe('2026-07');
        expect(shiftMonth(1)).toBe('2026-09');
    });

    it('shiftMonth rolls over the year boundary', () => {
        const dates = ref([]);
        const currentMonth = ref('2026-01');
        const { shiftMonth } = useAvailabilityCalendarGrid(dates, currentMonth);

        expect(shiftMonth(-1)).toBe('2025-12');
    });
});

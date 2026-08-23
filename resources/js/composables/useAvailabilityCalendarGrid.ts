import type { Ref } from 'vue';
import { computed } from 'vue';
import { isPastDate } from '@/lib/dates';

export type CalendarDay = { date: string; is_available: boolean };
export type CalendarCell = {
    date: string;
    day: number;
    isAvailable: boolean;
} | null;

/**
 * Grade de calendário (mês → dias com espaços em branco iniciais, dia
 * desabilitado quando já passou ou o backend não marcou como disponível)
 * compartilhada entre a busca pública (LandingAvailabilitySearch), o novo
 * agendamento do portal (patient-portal/appointments/Create) e o
 * reagendamento (patient-portal/appointments/Reschedule) — os três
 * renderizam a mesma grade a partir de fontes de dados diferentes.
 */
export function useAvailabilityCalendarGrid(
    dates: Ref<CalendarDay[]>,
    currentMonth: Ref<string>,
) {
    const monthLabel = computed(() => {
        const [year, month] = currentMonth.value.split('-').map(Number);

        return new Intl.DateTimeFormat('pt-BR', {
            month: 'long',
            year: 'numeric',
        }).format(new Date(year, month - 1, 1));
    });

    const calendarCells = computed(() => {
        const [year, month] = currentMonth.value.split('-').map(Number);
        const firstDay = new Date(year, month - 1, 1);
        const leadingBlanks = firstDay.getDay();
        const byDate = new Map(
            dates.value.map((day) => [day.date, day.is_available]),
        );

        const blanks: CalendarCell[] = Array.from(
            { length: leadingBlanks },
            () => null,
        );
        const days: CalendarCell[] = dates.value.map((day) => ({
            date: day.date,
            day: Number(day.date.slice(8, 10)),
            isAvailable:
                (byDate.get(day.date) ?? false) && !isPastDate(day.date),
        }));

        return blanks.concat(days);
    });

    function shiftMonth(offset: number): string {
        const [year, month] = currentMonth.value.split('-').map(Number);
        const shifted = new Date(year, month - 1 + offset, 1);

        return `${shifted.getFullYear()}-${String(shifted.getMonth() + 1).padStart(2, '0')}`;
    }

    return { monthLabel, calendarCells, shiftMonth };
}

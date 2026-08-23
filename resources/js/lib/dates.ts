export function todayDateString(): string {
    return new Date().toISOString().slice(0, 10);
}

/** `dateOnly` no formato `YYYY-MM-DD` — comparação lexicográfica é
 * suficiente (mesmo formato, ordenação cronológica). */
export function isPastDate(dateOnly: string): boolean {
    return dateOnly < todayDateString();
}

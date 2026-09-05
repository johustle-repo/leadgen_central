const MONTH_NAMES = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

/**
 * Every `recorded_at` timestamp is stored and computed on naively -
 * whatever hour/minute is in the database is the intended office
 * wall-clock time, regardless of the "+00:00" the app's UTC timezone
 * config tags it with. `Date`'s local-timezone getters (`getHours()`,
 * `toLocaleTimeString()`, ...) would silently shift that value by the
 * viewer's own browser/OS timezone offset, so every display and
 * edit-prefill in the attendance UI must read it back with the UTC
 * getters instead, to show the raw stored hour verbatim for every
 * viewer regardless of where they are.
 */
function pad(value: number): string {
    return String(value).padStart(2, '0');
}

export function formatAttendanceTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);

    return `${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}`;
}

export function formatAttendanceDateTime(iso: string): string {
    const date = new Date(iso);

    return `${MONTH_NAMES[date.getUTCMonth()]} ${date.getUTCDate()}, ${date.getUTCFullYear()} ${formatAttendanceTime(iso)}`;
}

export function toAttendanceDatetimeLocalValue(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}T${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}`;
}

import { formatLocale } from '@/lib/i18n';

/**
 * The day something happened, in the reader's language.
 *
 * Evidence and reviews are read against dates, not times, so the day is
 * as precise as these need to be. Empty for a moment that has not come.
 */
export function formatDay(timestamp: string | null): string {
    if (timestamp === null) {
        return '';
    }

    return new Date(timestamp).toLocaleDateString(formatLocale(), {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

/**
 * Show a file size the way the person who picked the file thinks of it.
 */
export function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const kilobytes = bytes / 1024;

    return kilobytes < 1024
        ? `${Math.round(kilobytes)} KB`
        : `${(kilobytes / 1024).toFixed(1)} MB`;
}

/**
 * How long ago something happened, in the largest unit that is not zero:
 * "12 days ago" is what a reader weighs, and the exact minute is not.
 */
export function formatRelative(timestamp: string | null): string | null {
    if (timestamp === null) {
        return null;
    }

    const seconds = (new Date(timestamp).getTime() - Date.now()) / 1000;
    const format = new Intl.RelativeTimeFormat(formatLocale(), {
        numeric: 'auto',
    });
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31_536_000],
        ['month', 2_592_000],
        ['week', 604_800],
        ['day', 86_400],
        ['hour', 3_600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return format.format(Math.round(seconds / size), unit);
        }
    }

    return format.format(0, 'minute');
}

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

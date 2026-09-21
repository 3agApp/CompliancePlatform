import { router } from '@inertiajs/react';

/**
 * Drop the prefetch cache whenever a write lands.
 *
 * The sidebar links prefetch on hover and Inertia caches each page by URL
 * for 30 seconds. Nothing invalidates that cache on its own: a visit only
 * clears what it names in invalidateCacheTags. So hovering a link, writing
 * something that changes the page behind it, and clicking within the window
 * serves the copy from before the write — the products page kept offering
 * "Invite a supplier" after a supplier had just been invited.
 *
 * Tagging every form and every link would work, but it has to be spelled out
 * in two places per page and the next form added brings the bug back. The
 * prefetch is only ever an optimization, so throwing it away after a write
 * costs one refetch and cannot go stale.
 *
 * Call before createInertiaApp, so a write on the very first page is covered.
 */
export function installPrefetchInvalidation(): void {
    /**
     * finish is the only global event whose detail carries the visit, and so
     * the method. Cancelled and interrupted visits never reached the server,
     * and a validation failure is worth flushing anyway: losing a cache entry
     * costs a refetch, keeping a stale one costs a wrong page.
     */
    router.on('finish', (event) => {
        const visit = event.detail.visit;

        if (visit.method !== 'get' && visit.completed) {
            router.flushAll();
        }
    });
}

import { router } from '@inertiajs/react';

/**
 * Going Back (or Forward) to a page makes Inertia restore it exactly as it was
 * left, so a list opened before editing a lead would still show the old
 * values. This re-fetches the restored page's props once it is back on
 * screen, keeping its scroll position, filters and local state.
 */
export function refreshPagesOnReturn(): void {
    if (typeof window === 'undefined') {
        return;
    }

    let returningFromHistory = false;

    window.addEventListener('popstate', (event) => {
        // A null state is a hash change, which Inertia does not restore.
        returningFromHistory = event.state !== null;
    });

    // Any ordinary visit supersedes a pending history restore.
    router.on('before', () => {
        returningFromHistory = false;
    });

    router.on('navigate', () => {
        if (returningFromHistory) {
            returningFromHistory = false;
            router.reload();
        }
    });

    // The browser's back-forward cache restores the whole page without a
    // popstate event, so refresh those returns too.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            router.reload();
        }
    });
}

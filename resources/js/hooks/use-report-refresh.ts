import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { version as reportVersionRoute } from '@/routes/report';

/** How often an open report checks whether its data changed. */
const CHECK_INTERVAL_MS = 5000;

/**
 * Keeps an open report current without a manual reload: it checks the report
 * data version every few seconds (and whenever the tab regains focus) and
 * reloads the page's props only when leads or uploads actually changed.
 */
export function useReportRefresh(renderedVersion: string): void {
    const knownVersion = useRef(renderedVersion);

    useEffect(() => {
        knownVersion.current = renderedVersion;
    }, [renderedVersion]);

    useEffect(() => {
        let checking = false;

        const check = async () => {
            if (checking || document.visibilityState !== 'visible') {
                return;
            }

            checking = true;

            try {
                const response = await fetch(reportVersionRoute.url(), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const { version } = (await response.json()) as {
                    version: string;
                };

                if (version !== knownVersion.current) {
                    knownVersion.current = version;
                    router.reload();
                }
            } catch {
                // Offline or signed out; the next check tries again.
            } finally {
                checking = false;
            }
        };

        const timer = window.setInterval(check, CHECK_INTERVAL_MS);
        document.addEventListener('visibilitychange', check);
        window.addEventListener('focus', check);

        return () => {
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', check);
            window.removeEventListener('focus', check);
        };
    }, []);
}

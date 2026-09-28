import { useSyncExternalStore } from 'react';

/** Mirrors HandleAppearance::ACCENTS and the [data-accent] rules in app.css. */
export const ACCENTS = [
    { value: 'ocean', label: 'Ocean', swatch: 'oklch(0.65 0.16 220)' },
    { value: 'blue', label: 'Blue', swatch: 'oklch(0.65 0.16 258)' },
    { value: 'violet', label: 'Violet', swatch: 'oklch(0.65 0.16 292)' },
    { value: 'emerald', label: 'Emerald', swatch: 'oklch(0.65 0.16 162)' },
    { value: 'amber', label: 'Amber', swatch: 'oklch(0.65 0.16 70)' },
    { value: 'rose', label: 'Rose', swatch: 'oklch(0.65 0.16 12)' },
    {
        value: 'midnight',
        label: 'Midnight',
        swatch: 'linear-gradient(135deg, oklch(0.2 0.045 262) 50%, oklch(0.8 0.14 150) 50%)',
    },
] as const;

export type Accent = (typeof ACCENTS)[number]['value'];

const DEFAULT_ACCENT: Accent = 'ocean';
const listeners = new Set<() => void>();
let currentAccent: Accent = DEFAULT_ACCENT;

const isAccent = (value: string | null): value is Accent =>
    ACCENTS.some((accent) => accent.value === value);

const applyAccent = (accent: Accent): void => {
    if (typeof document !== 'undefined') {
        document.documentElement.dataset.accent = accent;
    }
};

const readStoredAccent = (): Accent => {
    try {
        const stored = localStorage.getItem('accent');

        return isAccent(stored) ? stored : DEFAULT_ACCENT;
    } catch {
        return DEFAULT_ACCENT;
    }
};

export function initializeAccent(): void {
    if (typeof window === 'undefined') {
        return;
    }

    currentAccent = readStoredAccent();
    applyAccent(currentAccent);
}

export function useAccent(): {
    readonly accent: Accent;
    readonly updateAccent: (accent: Accent) => void;
} {
    const accent = useSyncExternalStore(
        (callback) => {
            listeners.add(callback);

            return () => listeners.delete(callback);
        },
        () => currentAccent,
        () => DEFAULT_ACCENT,
    );

    const updateAccent = (next: Accent): void => {
        currentAccent = next;

        try {
            localStorage.setItem('accent', next);
        } catch {
            // Storage can be unavailable (private mode); the cookie still applies it.
        }

        document.cookie = `accent=${next};path=/;max-age=${365 * 24 * 60 * 60};SameSite=Lax`;
        applyAccent(next);
        listeners.forEach((listener) => listener());
    };

    return { accent, updateAccent } as const;
}

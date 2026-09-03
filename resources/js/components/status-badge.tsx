const DEFAULT_COLORS = {
    success: 'bg-success/15 text-success dark:bg-success/20',
    destructive: 'bg-destructive/15 text-destructive dark:bg-destructive/20',
    warning: 'bg-warning/15 text-warning dark:bg-warning/20',
} as const;

function defaultColor(value: string): string {
    if (
        value === 'active' ||
        value === 'accepted' ||
        value === 'completed' ||
        value === 'valid'
    ) {
        return DEFAULT_COLORS.success;
    }

    if (
        value === 'failed' ||
        value === 'error' ||
        value === 'inactive' ||
        value === 'rejected'
    ) {
        return DEFAULT_COLORS.destructive;
    }

    return DEFAULT_COLORS.warning;
}

/**
 * `colorClass` lets callers with more than the default 3 status buckets
 * (e.g. reply classifications) supply their own Tailwind classes instead of
 * maintaining a separate badge component.
 */
export function StatusBadge({
    value,
    colorClass,
}: {
    value: string;
    colorClass?: string;
}) {
    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize ${colorClass ?? defaultColor(value)}`}
        >
            {value.replaceAll('_', ' ')}
        </span>
    );
}

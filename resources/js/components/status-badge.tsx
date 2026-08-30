export function StatusBadge({ value }: { value: string }) {
    const color =
        value === 'active' ||
        value === 'accepted' ||
        value === 'completed' ||
        value === 'valid'
            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
            : value === 'failed' ||
                value === 'error' ||
                value === 'inactive' ||
                value === 'rejected'
              ? 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300'
              : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300';

    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize ${color}`}
        >
            {value.replaceAll('_', ' ')}
        </span>
    );
}

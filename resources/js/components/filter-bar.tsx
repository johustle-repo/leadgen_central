import type { LucideIcon } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

type FilterBarProps = {
    icon?: LucideIcon;
    label: string;
    hint?: ReactNode;
    gridClassName?: string;
    children: ReactNode;
} & (
    | {
          as?: 'form';
          id?: string;
          onSubmit?: (event: FormEvent<HTMLFormElement>) => void;
      }
    | { as: 'div'; id?: never; onSubmit?: never }
);

/**
 * Shared filter-toolbar shell: an uppercase eyebrow label, a field grid, and
 * an optional hint line below. Proven first on the dashboard's "Reporting
 * period" card. Pass `as="div"` for pages that mix a form with non-form
 * controls (e.g. status tabs) inside the same card.
 */
export function FilterBar(props: FilterBarProps) {
    const {
        icon: Icon,
        label,
        hint,
        gridClassName = 'sm:grid-cols-4',
        children,
    } = props;
    const body = (
        <>
            <div className="mb-3 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {Icon && <Icon className="size-3.5" />}
                {label}
            </div>
            <div className={cn('grid gap-3', gridClassName)}>{children}</div>
            {hint && (
                <p className="mt-3 text-xs text-muted-foreground">{hint}</p>
            )}
        </>
    );

    if (props.as === 'div') {
        return <div className="rounded-xl border bg-card p-4">{body}</div>;
    }

    return (
        <form
            id={props.id}
            onSubmit={props.onSubmit}
            className="rounded-xl border bg-card p-4"
        >
            {body}
        </form>
    );
}

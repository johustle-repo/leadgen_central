import { ChevronDown } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
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
    const [open, setOpen] = useState(false);
    const body = (
        <>
            {/* On phones the fields fold away behind this toggle so the data
                is visible first; from md up they are always shown. */}
            <button
                type="button"
                onClick={() => setOpen((current) => !current)}
                aria-expanded={open}
                className="flex w-full items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase md:pointer-events-none md:mb-3"
            >
                {Icon && <Icon className="size-3.5" />}
                {label}
                <ChevronDown
                    className={cn(
                        'ml-auto size-4 transition-transform md:hidden',
                        open && 'rotate-180',
                    )}
                    aria-hidden="true"
                />
            </button>
            <div className={cn('mt-3 md:mt-0 md:block', !open && 'hidden')}>
                <div className={cn('grid gap-3', gridClassName)}>
                    {children}
                </div>
                {hint && (
                    <p className="mt-3 text-xs text-muted-foreground">{hint}</p>
                )}
            </div>
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

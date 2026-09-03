import * as React from 'react';
import { cn } from '@/lib/utils';

/**
 * Shared table primitives. Bakes in the look proven on the dashboard's
 * "Agent productivity" table: a tinted header, divided rows, row hover, and
 * an `align="right"` convenience for numeric columns (`tabular-nums`).
 *
 * By default `Table` wraps itself in a bordered card, since most pages use
 * it as their only content in that slot. Pass `bare` when nesting inside a
 * `Card` that already provides that chrome (e.g. `CardContent`), so the
 * borders/background don't double up.
 */
function Table({
    className,
    bare = false,
    ...props
}: React.ComponentProps<'table'> & { bare?: boolean }) {
    const table = (
        <table
            data-slot="table"
            className={cn('w-full text-sm', className)}
            {...props}
        />
    );

    if (bare) {
        return <div className="overflow-x-auto">{table}</div>;
    }

    return (
        <div className="overflow-hidden rounded-xl border bg-card">
            <div className="overflow-x-auto">{table}</div>
        </div>
    );
}

function TableHeader({ className, ...props }: React.ComponentProps<'thead'>) {
    return (
        <thead
            data-slot="table-header"
            className={cn('bg-muted/60 text-left', className)}
            {...props}
        />
    );
}

function TableBody({ className, ...props }: React.ComponentProps<'tbody'>) {
    return (
        <tbody
            data-slot="table-body"
            className={cn('divide-y', className)}
            {...props}
        />
    );
}

function TableRow({ className, ...props }: React.ComponentProps<'tr'>) {
    return (
        <tr
            data-slot="table-row"
            className={cn('transition-colors hover:bg-muted/40', className)}
            {...props}
        />
    );
}

function TableHead({
    className,
    align = 'left',
    ...props
}: React.ComponentProps<'th'> & { align?: 'left' | 'right' | 'center' }) {
    return (
        <th
            data-slot="table-head"
            className={cn(
                'p-3 font-medium',
                align === 'right' && 'text-right',
                align === 'center' && 'text-center',
                className,
            )}
            {...props}
        />
    );
}

function TableCell({
    className,
    align = 'left',
    ...props
}: React.ComponentProps<'td'> & { align?: 'left' | 'right' | 'center' }) {
    return (
        <td
            data-slot="table-cell"
            className={cn(
                'p-3',
                align === 'right' && 'text-right tabular-nums',
                align === 'center' && 'text-center tabular-nums',
                className,
            )}
            {...props}
        />
    );
}

export { Table, TableHeader, TableBody, TableRow, TableHead, TableCell };

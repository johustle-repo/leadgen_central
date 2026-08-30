import type { ReactNode } from 'react';

export function PageHeader({
    title,
    description,
    actions,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
}) {
    return (
        <div className="relative flex flex-col justify-between gap-4 border-b border-border/50 pb-5 sm:flex-row sm:items-end">
            <div className="relative pl-4 before:absolute before:inset-y-1 before:left-0 before:w-1 before:rounded-full before:bg-gradient-to-b before:from-cyan-400 before:to-indigo-500 before:shadow-[0_0_14px_rgba(34,211,238,0.35)]">
                <p className="mb-1 text-[10px] font-bold tracking-[0.22em] text-cyan-600 uppercase dark:text-cyan-300">
                    LeadGen Central
                </p>
                <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                    {title}
                </h1>
                {description && (
                    <p className="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions}
        </div>
    );
}

import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

/**
 * Shared section shell for the Dashboard and Reports pages. Most sections stay
 * always-visible; passing `collapsible` lets the denser, less-checked-daily ones
 * start closed so the page reads as "headline first, detail on demand" instead of
 * every panel competing for attention at once. The `note` caption stays visible
 * either way, so collapsing a section never hides its one-line context.
 */
export function Section({
    title,
    note,
    children,
    collapsible = false,
    defaultOpen = true,
}: {
    title: string;
    note: string;
    children: ReactNode;
    collapsible?: boolean;
    defaultOpen?: boolean;
}) {
    if (!collapsible) {
        return (
            <section className="flex min-w-0 flex-col gap-4">
                <div>
                    <h2 className="text-lg font-semibold tracking-tight">
                        {title}
                    </h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {note}
                    </p>
                </div>
                {children}
            </section>
        );
    }

    return (
        <Collapsible
            defaultOpen={defaultOpen}
            className="min-w-0 rounded-xl border bg-card"
        >
            <CollapsibleTrigger className="group flex w-full items-start justify-between gap-3 p-4 text-left hover:bg-muted/40">
                <div>
                    <h2 className="text-lg font-semibold tracking-tight">
                        {title}
                    </h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {note}
                    </p>
                </div>
                <ChevronDown className="mt-1 size-4 shrink-0 text-muted-foreground transition-transform group-data-[state=open]:rotate-180" />
            </CollapsibleTrigger>
            <CollapsibleContent className="flex min-w-0 flex-col gap-4 px-4 pb-4">
                {children}
            </CollapsibleContent>
        </Collapsible>
    );
}

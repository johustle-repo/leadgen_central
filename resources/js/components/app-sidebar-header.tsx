import { Breadcrumbs } from '@/components/breadcrumbs';
import { HeaderActionsSlot } from '@/components/header-actions';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="sticky top-0 z-20 flex min-h-16 shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-border/60 bg-background/75 px-4 py-2 shadow-sm shadow-slate-950/3 backdrop-blur-xl transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:min-h-14 md:px-6">
            <div className="flex min-w-0 items-center gap-3">
                <SidebarTrigger className="-ml-1 rounded-xl border border-border/70 bg-card/70 shadow-sm hover:bg-accent" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex flex-wrap items-center justify-end gap-3">
                <HeaderActionsSlot className="hidden flex-wrap items-center gap-2 md:flex" />
                <div className="hidden items-center gap-2 rounded-full border border-cyan-500/15 bg-cyan-500/7 px-3 py-1.5 text-[11px] font-semibold tracking-wide text-cyan-700 uppercase md:flex dark:text-cyan-300">
                    <span className="size-1.5 rounded-full bg-emerald-400 shadow-[0_0_8px_var(--color-emerald-400)]" />
                    Lead intelligence workspace
                </div>
            </div>
        </header>
    );
}

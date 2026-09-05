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
            </div>
        </header>
    );
}

import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { HeaderActionsProvider } from '@/components/header-actions';
import { ImpersonationBanner } from '@/components/impersonation-banner';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <HeaderActionsProvider>
                <AppSidebar />
                <AppContent
                    variant="sidebar"
                    className="app-workspace relative min-w-0 overflow-x-clip"
                >
                    <ImpersonationBanner />
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    {children}
                </AppContent>
            </HeaderActionsProvider>
        </AppShell>
    );
}

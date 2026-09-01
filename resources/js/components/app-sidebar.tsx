import { Link } from '@inertiajs/react';
import { usePage, usePoll } from '@inertiajs/react';
import {
    CopyCheck,
    ChartNoAxesCombined,
    ClipboardList,
    FileClock,
    LayoutGrid,
    MailSearch,
    Mails,
    Settings,
    ShieldCheck,
    Upload,
    Users,
    Waypoints,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as analyticsIndex } from '@/routes/analytics';
import { index as auditLogIndex } from '@/routes/audit-logs';
import { index as duplicateIndex } from '@/routes/duplicates';
import { index as emailReplyIndex } from '@/routes/email-replies';
import { index as emailSequenceIndex } from '@/routes/email-sequences';
import { index as leadIndex } from '@/routes/leads';
import { edit as settingsEdit } from '@/routes/system-settings';
import { create as uploadCreate, index as uploadIndex } from '@/routes/uploads';
import { index as userIndex } from '@/routes/users';
import { index as verificationIndex } from '@/routes/verification';
import type { NavItem } from '@/types';
import type { Auth } from '@/types';

export function AppSidebar() {
    usePoll(60000, { only: ['notificationCounts'] });

    const { auth, notificationCounts } = usePage<{
        auth: Auth;
        notificationCounts: { unread_email_replies: number };
    }>().props;
    const mainNavItems: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        {
            title: 'Analytics',
            href: analyticsIndex(),
            icon: ChartNoAxesCombined,
        },
        { title: 'Leads', href: leadIndex(), icon: Waypoints },
        {
            title: 'Email Replies',
            href: emailReplyIndex(),
            icon: MailSearch,
            badge: notificationCounts.unread_email_replies,
        },
        { title: 'Email Sequences', href: emailSequenceIndex(), icon: Mails },
        { title: 'Upload Leads', href: uploadCreate(), icon: Upload },
        { title: 'Upload History', href: uploadIndex(), icon: FileClock },
    ];

    if (auth.user.role !== 'agent') {
        mainNavItems.push(
            {
                title: 'Verification',
                href: verificationIndex(),
                icon: ShieldCheck,
            },
            {
                title: 'Duplicate Review',
                href: duplicateIndex(),
                icon: CopyCheck,
            },
        );
    }

    if (auth.user.role === 'administrator') {
        mainNavItems.push(
            { title: 'Users', href: userIndex(), icon: Users },
            {
                title: 'Audit Logs',
                href: auditLogIndex(),
                icon: ClipboardList,
            },
            { title: 'Settings', href: settingsEdit(), icon: Settings },
        );
    }

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="[&_[data-sidebar=sidebar]]:border [&_[data-sidebar=sidebar]]:border-white/8 [&_[data-sidebar=sidebar]]:shadow-2xl [&_[data-sidebar=sidebar]]:shadow-slate-950/20"
        >
            <SidebarHeader className="border-b border-white/8 p-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="h-12 rounded-xl hover:bg-white/8"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="px-1 py-4">
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter className="border-t border-white/8 p-3">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

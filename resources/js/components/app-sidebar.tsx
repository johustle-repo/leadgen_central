import { Link } from '@inertiajs/react';
import { usePage, usePoll } from '@inertiajs/react';
import {
    CopyCheck,
    CalendarClock,
    ChartNoAxesCombined,
    ClipboardList,
    FileClock,
    LayoutGrid,
    MailSearch,
    QrCode,
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
import { isAdministratorRole } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    index as attendanceIndex,
    scanner as attendanceScanner,
    summary as attendanceSummary,
} from '@/routes/attendance';
import { index as auditLogIndex } from '@/routes/audit-logs';
import { index as duplicateIndex } from '@/routes/duplicates';
import { index as emailReplyIndex } from '@/routes/email-replies';
import { index as leadIndex } from '@/routes/leads';
import { index as reportIndex } from '@/routes/report';
import { create as uploadCreate, index as uploadIndex } from '@/routes/uploads';
import { index as userIndex } from '@/routes/users';
import { index as verificationIndex } from '@/routes/verification';
import type { NavGroup } from '@/types';
import type { Auth } from '@/types';

export function AppSidebar() {
    usePoll(60000, { only: ['notificationCounts'] });

    const { auth, notificationCounts, appVersion } = usePage<{
        auth: Auth;
        notificationCounts: { unread_email_replies: number };
        appVersion: string;
    }>().props;
    const isSuperAdministrator = auth.user.role === 'super_administrator';
    const navGroups: NavGroup[] = [
        {
            label: 'Overview',
            items: [
                { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
                {
                    title: 'Lead Reports',
                    href: reportIndex(),
                    icon: ChartNoAxesCombined,
                },
            ],
        },
        {
            label: 'Leads',
            items: [
                { title: 'Leads', href: leadIndex(), icon: Waypoints },
                // Email Replies is a Super Administrator-only feature; every
                // other role has it hidden entirely, not just unlinked.
                ...(isSuperAdministrator
                    ? [
                          {
                              title: 'Email Replies',
                              href: emailReplyIndex(),
                              icon: MailSearch,
                              badge: notificationCounts.unread_email_replies,
                          },
                      ]
                    : []),
                {
                    title: 'Upload Leads',
                    href: uploadCreate(),
                    icon: Upload,
                },
                {
                    title: 'Upload History',
                    href: uploadIndex(),
                    icon: FileClock,
                },
                // Verification and duplicate review are review steps agents
                // don't perform themselves, so they're hidden for that role.
                ...(auth.user.role !== 'agent'
                    ? [
                          {
                              title: 'Lead Verification',
                              href: verificationIndex(),
                              icon: ShieldCheck,
                          },
                          {
                              title: 'Duplicate Review',
                              href: duplicateIndex(),
                              icon: CopyCheck,
                          },
                      ]
                    : []),
            ],
        },
    ];

    if (isAdministratorRole(auth.user.role)) {
        navGroups.push({
            label: 'Administration',
            items: [
                { title: 'Users', href: userIndex(), icon: Users },
                {
                    title: 'Audit Logs',
                    href: auditLogIndex(),
                    icon: ClipboardList,
                },
            ],
        });
    }

    if (isSuperAdministrator) {
        navGroups.push({
            label: 'Attendance',
            items: [
                {
                    title: 'QR Scanner',
                    href: attendanceScanner(),
                    icon: QrCode,
                },
                {
                    title: 'Attendance',
                    href: attendanceIndex(),
                    icon: FileClock,
                },
                {
                    title: 'Attendance Summary',
                    href: attendanceSummary(),
                    icon: CalendarClock,
                },
            ],
        });
    }

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="[&_[data-sidebar=sidebar]]:border [&_[data-sidebar=sidebar]]:border-white/8"
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
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter className="border-t border-white/8 p-3">
                <NavUser />
                <p className="mt-2 px-1 text-center text-[10px] text-sidebar-foreground/40 group-data-[collapsible=icon]:hidden">
                    v{appVersion}
                </p>
            </SidebarFooter>
        </Sidebar>
    );
}

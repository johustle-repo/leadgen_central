import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavGroup } from '@/types';

export function NavMain({ groups }: { groups: NavGroup[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <div className="flex flex-col gap-3">
            {groups.map((group) => (
                <SidebarGroup key={group.label} className="px-2 py-0">
                    <SidebarGroupLabel className="mb-2 px-3 text-[10px] font-bold tracking-[0.2em] text-cyan-200/55 uppercase">
                        {group.label}
                    </SidebarGroupLabel>
                    <SidebarMenu className="gap-1.5">
                        {group.items.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentUrl(item.href)}
                                    tooltip={{ children: item.title }}
                                    className="h-10 rounded-xl border-l-3 border-transparent px-3 text-sidebar-foreground/70 transition-all hover:bg-white/8 hover:text-white data-[active=true]:border-cyan-400 data-[active=true]:bg-cyan-400/12 data-[active=true]:font-semibold data-[active=true]:text-cyan-100"
                                >
                                    <Link href={item.href} prefetch>
                                        {item.icon && (
                                            <item.icon className="size-4.5" />
                                        )}
                                        <span>{item.title}</span>
                                        {!!item.badge && (
                                            <span className="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-400 px-1.5 py-0.5 text-[10px] leading-none font-bold text-emerald-950">
                                                {item.badge > 99
                                                    ? '99+'
                                                    : item.badge}
                                            </span>
                                        )}
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </div>
    );
}

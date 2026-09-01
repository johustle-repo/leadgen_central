import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel className="mb-2 px-3 text-[10px] font-bold tracking-[0.2em] text-cyan-200/55 uppercase">
                Workspace
            </SidebarGroupLabel>
            <SidebarMenu className="gap-1.5">
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentUrl(item.href)}
                            tooltip={{ children: item.title }}
                            className="h-10 rounded-xl px-3 text-sidebar-foreground/70 transition-all hover:bg-white/8 hover:text-white data-[active=true]:bg-gradient-to-r data-[active=true]:from-cyan-400/18 data-[active=true]:to-sky-400/8 data-[active=true]:font-semibold data-[active=true]:text-cyan-100 data-[active=true]:shadow-[inset_3px_0_0_0_var(--color-cyan-400)]"
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && (
                                    <item.icon className="size-4.5" />
                                )}
                                <span>{item.title}</span>
                                {!!item.badge && (
                                    <span className="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-400 px-1.5 py-0.5 text-[10px] leading-none font-bold text-emerald-950 shadow-sm shadow-emerald-950/20">
                                        {item.badge > 99 ? '99+' : item.badge}
                                    </span>
                                )}
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

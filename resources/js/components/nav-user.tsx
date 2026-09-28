import { Link, router, usePage } from '@inertiajs/react';
import { Crown, LogOut, Moon, Sun } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useSidebar } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/hooks/use-appearance';
import { useInitials } from '@/hooks/use-initials';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';

const ROLE_LABELS: Record<string, string> = {
    super_administrator: 'Super Admin',
    administrator: 'Administrator',
    sub_administrator: 'Sub Admin',
    agent: 'Agent',
};

/**
 * Sidebar footer profile card: photo, name and role link to profile
 * settings, with light/dark and log-out shortcuts beside it. Collapses to
 * just the photo when the sidebar is collapsed to icons.
 */
export function NavUser() {
    const { auth } = usePage().props;
    const { state, isMobile } = useSidebar();
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const getInitials = useInitials();
    const cleanup = useMobileNavigation();

    if (!auth.user) {
        return null;
    }

    const user = auth.user;
    const isSuperAdministrator = user.role === 'super_administrator';
    const collapsed = state === 'collapsed' && !isMobile;
    const isDark = resolvedAppearance === 'dark';
    const avatar = (
        <Avatar
            className={cn(
                'size-9 shrink-0 overflow-hidden rounded-full ring-2 ring-offset-2 ring-offset-sidebar',
                isSuperAdministrator
                    ? 'ring-amber-400'
                    : 'ring-sidebar-primary',
                collapsed && 'size-8 ring-offset-1',
            )}
        >
            <AvatarImage
                src={user.avatar ?? undefined}
                alt=""
                className="object-cover"
            />
            <AvatarFallback className="bg-sidebar-accent text-sm font-semibold text-sidebar-accent-foreground">
                {getInitials(user.name)}
            </AvatarFallback>
        </Avatar>
    );

    if (collapsed) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <Link
                        href={edit()}
                        className="mx-auto rounded-full focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:outline-none"
                        aria-label={`${user.name} · profile settings`}
                    >
                        {avatar}
                    </Link>
                </TooltipTrigger>
                <TooltipContent side="right">{user.name}</TooltipContent>
            </Tooltip>
        );
    }

    return (
        <div className="flex flex-col gap-1.5">
            <div className="flex items-center gap-0.5">
                <Link
                    href={edit()}
                    prefetch
                    onClick={cleanup}
                    className="flex min-w-0 flex-1 items-center gap-2.5 rounded-2xl border border-sidebar-foreground/25 bg-sidebar-accent/40 px-2 py-2 transition-colors hover:border-sidebar-primary/70 hover:bg-sidebar-accent focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:outline-none"
                    data-test="sidebar-menu-button"
                >
                    {avatar}
                    <span className="grid min-w-0 leading-tight">
                        <span className="flex min-w-0 items-center gap-1 text-sm font-semibold text-sidebar-foreground">
                            <span className="truncate">{user.name}</span>
                            {isSuperAdministrator && (
                                <Crown className="size-3.5 shrink-0 fill-amber-400 text-amber-500" />
                            )}
                        </span>
                        <span className="truncate text-xs text-sidebar-primary">
                            {ROLE_LABELS[user.role] ?? user.role}
                        </span>
                    </span>
                </Link>
                <button
                    type="button"
                    onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
                    className="flex size-8 shrink-0 items-center justify-center rounded-xl text-sidebar-foreground/80 transition-colors hover:bg-sidebar-accent hover:text-sidebar-foreground"
                    aria-label={
                        isDark ? 'Switch to light mode' : 'Switch to dark mode'
                    }
                    title={isDark ? 'Light mode' : 'Dark mode'}
                >
                    {isDark ? (
                        <Sun className="size-4.5" />
                    ) : (
                        <Moon className="size-4.5" />
                    )}
                </button>
                <Link
                    href={logout()}
                    as="button"
                    onClick={() => {
                        cleanup();
                        router.flushAll();
                    }}
                    className="flex size-8 shrink-0 items-center justify-center rounded-xl text-sidebar-foreground/80 transition-colors hover:bg-destructive/15 hover:text-destructive"
                    aria-label="Log out"
                    title="Log out"
                    data-test="logout-button"
                >
                    <LogOut className="size-4.5" />
                </Link>
            </div>
        </div>
    );
}

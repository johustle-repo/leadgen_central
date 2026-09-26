import { Link, usePage } from '@inertiajs/react';
import { Palette, ShieldCheck, UserRound } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { Auth, NavItem } from '@/types';

const DESCRIPTIONS: Record<string, string> = {
    Profile: 'Photo, name and email',
    Security: 'Password and two-factor',
    Appearance: 'Theme and accent colour',
};

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { auth } = usePage<{ auth: Auth }>().props;
    const getInitials = useInitials();

    const sidebarNavItems: NavItem[] = [
        { title: 'Profile', href: edit(), icon: UserRound },
        { title: 'Security', href: editSecurity(), icon: ShieldCheck },
        { title: 'Appearance', href: editAppearance(), icon: Palette },
        // QR Attendance (agent self-service badge scanning) is temporarily
        // disabled - see routes/settings.php.
    ];

    return (
        <div className="flex flex-col gap-6 bg-muted/40 p-4 md:p-6">
            <header className="accent-banner flex items-center gap-4 rounded-xl px-5 py-4 shadow-xs">
                <Avatar className="size-12 shrink-0 overflow-hidden rounded-full ring-2 ring-primary-foreground/40">
                    <AvatarImage
                        src={auth.user.avatar ?? undefined}
                        alt=""
                        className="object-cover"
                    />
                    <AvatarFallback className="bg-primary-foreground/15 font-semibold">
                        {getInitials(auth.user.name)}
                    </AvatarFallback>
                </Avatar>
                <div className="min-w-0">
                    <h1 className="truncate text-xl font-semibold tracking-tight">
                        Settings
                    </h1>
                    <p className="truncate text-sm opacity-85">
                        {auth.user.name} · {auth.user.email}
                    </p>
                </div>
            </header>

            <div className="flex flex-col gap-6 lg:flex-row">
                <nav
                    className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:w-60 lg:shrink-0 lg:flex-col lg:self-start lg:overflow-visible lg:rounded-xl lg:border lg:bg-card lg:p-2 lg:shadow-xs"
                    aria-label="Settings"
                >
                    {sidebarNavItems.map((item, index) => {
                        const active = isCurrentOrParentUrl(item.href);

                        return (
                            <Link
                                key={`${toUrl(item.href)}-${index}`}
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'flex shrink-0 items-center gap-3 rounded-lg border px-3 py-2 text-sm transition-colors lg:border-transparent',
                                    active
                                        ? 'border-primary bg-primary/10 font-semibold text-foreground'
                                        : 'bg-card text-muted-foreground hover:bg-muted hover:text-foreground lg:bg-transparent',
                                )}
                            >
                                {item.icon && (
                                    <item.icon
                                        className={cn(
                                            'size-4 shrink-0',
                                            active && 'text-primary',
                                        )}
                                    />
                                )}
                                <span className="flex flex-col">
                                    {item.title}
                                    <span className="hidden text-xs font-normal text-muted-foreground lg:block">
                                        {DESCRIPTIONS[item.title]}
                                    </span>
                                </span>
                            </Link>
                        );
                    })}
                </nav>

                <div className="min-w-0 flex-1 lg:max-w-3xl">
                    <section className="flex flex-col gap-6">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}

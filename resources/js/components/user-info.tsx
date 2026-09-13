import { Crown } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
    caption,
}: {
    user: User;
    showEmail?: boolean;
    caption?: string;
}) {
    const getInitials = useInitials();
    const isSuperAdministrator = user.role === 'super_administrator';

    return (
        <>
            <Avatar
                className={
                    isSuperAdministrator
                        ? 'h-8 w-8 overflow-hidden rounded-full ring-2 ring-amber-400 ring-offset-1 ring-offset-sidebar'
                        : 'h-8 w-8 overflow-hidden rounded-full'
                }
            >
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid min-w-0 flex-1 text-left text-sm leading-tight">
                <span className="flex min-w-0 items-center gap-1 font-medium">
                    <span className="truncate">{user.name}</span>
                    {isSuperAdministrator && (
                        <Crown className="size-3.5 shrink-0 fill-amber-400 text-amber-500" />
                    )}
                </span>
                {showEmail && (
                    <span className="truncate text-xs text-muted-foreground">
                        {user.email}
                    </span>
                )}
                {caption && (
                    <span className="truncate text-[10px] text-sidebar-foreground/40">
                        {caption}
                    </span>
                )}
            </div>
        </>
    );
}

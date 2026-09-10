import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';

/**
 * Shared "metric tile" used across Dashboard, Upload History, and Verification.
 * `tone` is a text-color utility class (e.g. `text-success`) applied to both
 * the icon and its tinted badge background via `bg-current`.
 *
 * When `href` is set, the whole tile becomes a link to that metric's detailed
 * view (e.g. the leads list filtered to match).
 */
export function StatTile({
    label,
    value,
    icon: Icon,
    tone = 'text-primary',
    detail,
    href,
}: {
    label: string;
    value: string | number;
    icon?: LucideIcon;
    tone?: string;
    detail?: ReactNode;
    href?: ComponentProps<typeof Link>['href'];
}) {
    const card = (
        <Card
            className={`relative overflow-hidden py-0 transition-shadow hover:shadow-md ${href ? 'cursor-pointer hover:ring-1 hover:ring-ring' : ''}`}
        >
            <div className={`absolute inset-x-0 top-0 h-1 bg-current ${tone}`} />
            <CardContent className="flex items-center justify-between gap-4 px-5 pt-6 pb-5">
                <div className="min-w-0">
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <p className="mt-1 text-3xl font-bold tracking-tight tabular-nums">
                        {typeof value === 'number'
                            ? value.toLocaleString()
                            : value}
                    </p>
                    {detail && (
                        <div className="mt-1 text-xs text-muted-foreground">
                            {detail}
                        </div>
                    )}
                </div>
                {Icon && (
                    <div
                        className={`flex size-11 shrink-0 items-center justify-center rounded-2xl bg-current/8 ${tone}`}
                    >
                        <Icon className="size-5" />
                    </div>
                )}
            </CardContent>
        </Card>
    );

    if (!href) {
        return card;
    }

    return (
        <Link href={href} className="block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            {card}
        </Link>
    );
}

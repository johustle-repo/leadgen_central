import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';

/**
 * Shared "metric tile" used across Dashboard, Upload History, and Verification.
 * `tone` is a text-color utility class (e.g. `text-success`) applied to both
 * the icon and its tinted badge background via `bg-current`.
 */
export function StatTile({
    label,
    value,
    icon: Icon,
    tone = 'text-primary',
    detail,
}: {
    label: string;
    value: string | number;
    icon?: LucideIcon;
    tone?: string;
    detail?: ReactNode;
}) {
    return (
        <Card>
            <CardContent className="flex items-center justify-between gap-4 p-5">
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
}

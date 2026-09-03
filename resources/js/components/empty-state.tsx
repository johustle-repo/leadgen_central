import type { LucideIcon } from 'lucide-react';

/**
 * Shared "nothing here" placeholder: icon + heading + one-line hint.
 * Modeled on the pattern already used by Email Replies and Verification.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
}: {
    icon: LucideIcon;
    title: string;
    description?: string;
}) {
    return (
        <div className="flex flex-col items-center gap-3 p-12 text-center">
            <Icon className="size-8 text-muted-foreground" />
            <div>
                <p className="font-medium">{title}</p>
                {description && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
        </div>
    );
}

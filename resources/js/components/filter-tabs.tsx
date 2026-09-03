import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';

/**
 * Shared "status filter tabs" used across Upload History and Verification.
 */
export function FilterTabs({
    tabs,
}: {
    tabs: Array<{
        label: string;
        href: ComponentProps<typeof Link>['href'];
        active: boolean;
    }>;
}) {
    return (
        <div className="flex flex-wrap gap-2">
            {tabs.map((tab) => (
                <Button
                    key={tab.label}
                    asChild
                    size="sm"
                    variant={tab.active ? 'default' : 'outline'}
                >
                    <Link href={tab.href}>{tab.label}</Link>
                </Button>
            ))}
        </div>
    );
}

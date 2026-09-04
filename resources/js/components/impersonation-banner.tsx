import { router, usePage } from '@inertiajs/react';
import { LogOut, UserRoundCog } from 'lucide-react';
import { stop as stopImpersonation } from '@/routes/impersonate';

export function ImpersonationBanner() {
    const { impersonator, auth } = usePage().props;

    if (!impersonator) {
        return null;
    }

    return (
        <div className="relative z-30 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 bg-gradient-to-r from-amber-500 to-orange-500 px-4 py-1.5 text-center text-xs font-semibold text-white">
            <UserRoundCog className="size-3.5 shrink-0" />
            <span>
                {impersonator.name} is viewing as {auth.user.name}
            </span>
            <button
                type="button"
                onClick={() => router.delete(stopImpersonation.url())}
                className="inline-flex items-center gap-1 rounded-full bg-white/20 px-2.5 py-0.5 hover:bg-white/30"
            >
                <LogOut className="size-3" />
                Return to my account
            </button>
        </div>
    );
}

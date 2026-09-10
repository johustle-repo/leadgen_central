import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name, auth } = usePage<{
        name: string;
        auth: { user: { company_alias?: string | null } };
    }>().props;

    return (
        <>
            <div className="flex aspect-square size-9 items-center justify-center rounded-xl bg-cyan-400 text-slate-950">
                <AppLogoIcon className="size-5" />
            </div>
            <div className="ml-1.5 grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-bold tracking-tight text-white">
                    {name === 'Laravel' ? 'LeadGen Central' : name}
                </span>
                <span className="truncate text-[10px] font-medium tracking-wide text-cyan-200/55 uppercase">
                    {auth.user.company_alias || 'Lead operations'}
                </span>
            </div>
        </>
    );
}

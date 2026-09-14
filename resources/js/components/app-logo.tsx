import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name, auth } = usePage<{
        name: string;
        auth: { user: { company_alias?: string | null } };
    }>().props;

    return (
        <>
            <AppLogoIcon className="aspect-square size-9 object-contain" />
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

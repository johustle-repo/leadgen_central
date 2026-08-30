import { Link } from '@inertiajs/react';
import { BadgeCheck, DatabaseZap, LockKeyhole, ScanSearch } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="grid min-h-svh bg-[#07111f] text-white lg:grid-cols-[1.05fr_.95fr]">
            <aside className="relative hidden overflow-hidden border-r border-white/8 p-12 lg:flex lg:flex-col lg:justify-between xl:p-16">
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_15%_5%,rgba(34,211,238,.18),transparent_35%),radial-gradient(circle_at_80%_80%,rgba(99,102,241,.18),transparent_32%)]" />
                <div className="absolute inset-0 [background-image:linear-gradient(rgba(148,163,184,.12)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,.12)_1px,transparent_1px)] [background-size:52px_52px] opacity-20" />
                <Link
                    href={home()}
                    className="relative flex items-center gap-3"
                >
                    <span className="flex size-11 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-300 to-indigo-500">
                        <AppLogoIcon className="size-7 text-slate-950" />
                    </span>
                    <span className="text-xl font-semibold">
                        LeadGen <span className="text-cyan-300">Central</span>
                    </span>
                </Link>
                <div className="relative max-w-xl">
                    <span className="inline-flex items-center gap-2 rounded-full border border-cyan-300/20 bg-cyan-300/8 px-3 py-1.5 text-xs text-cyan-200">
                        <DatabaseZap className="size-3.5" />
                        Your lead operation, unified
                    </span>
                    <h2 className="mt-7 text-4xl leading-tight font-semibold tracking-[-0.035em] xl:text-5xl">
                        Better lead data starts with a better system.
                    </h2>
                    <p className="mt-5 max-w-lg text-base leading-7 text-slate-300">
                        Give your team one secure workspace to import, validate,
                        organize, and act on every opportunity.
                    </p>
                    <div className="mt-9 grid gap-4 sm:grid-cols-2">
                        {[
                            [ScanSearch, 'Structured validation'],
                            [LockKeyhole, 'Role-based security'],
                            [BadgeCheck, 'Reliable ownership'],
                            [DatabaseZap, 'Complete upload history'],
                        ].map(([Icon, label]) => {
                            const FeatureIcon = Icon as typeof ScanSearch;

                            return (
                                <div
                                    key={String(label)}
                                    className="flex items-center gap-3 text-sm text-slate-300"
                                >
                                    <span className="flex size-9 items-center justify-center rounded-lg border border-white/10 bg-white/5 text-cyan-300">
                                        <FeatureIcon className="size-4" />
                                    </span>
                                    {label as string}
                                </div>
                            );
                        })}
                    </div>
                </div>
                <p className="relative text-xs text-slate-500">
                    Secure lead generation, validation, and intelligence
                    management.
                </p>
            </aside>

            <main className="relative flex min-h-svh items-center justify-center overflow-hidden bg-slate-50 px-5 py-10 text-slate-950 sm:px-8 dark:bg-[#091421] dark:text-white">
                <div className="pointer-events-none absolute top-0 right-0 size-80 rounded-full bg-cyan-300/10 blur-3xl" />
                <div className="relative w-full max-w-md">
                    <Link
                        href={home()}
                        className="mb-10 flex items-center justify-center gap-3 lg:hidden"
                    >
                        <span className="flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-300 to-indigo-500">
                            <AppLogoIcon className="size-6 text-slate-950" />
                        </span>
                        <span className="text-lg font-semibold">
                            LeadGen Central
                        </span>
                    </Link>
                    <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl shadow-slate-900/8 sm:p-8 dark:border-white/10 dark:bg-white/[.045] dark:shadow-black/30">
                        <div className="mb-8">
                            <p className="mb-3 text-xs font-semibold tracking-[.18em] text-cyan-600 uppercase dark:text-cyan-300">
                                Secure workspace
                            </p>
                            <h1 className="text-3xl font-semibold tracking-[-0.03em]">
                                {title}
                            </h1>
                            <p className="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                {description}
                            </p>
                        </div>
                        {children}
                    </div>
                    <p className="mt-6 text-center text-xs text-slate-400">
                        Protected by encrypted sessions and role-based
                        authorization.
                    </p>
                </div>
            </main>
        </div>
    );
}

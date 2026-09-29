import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    ChartNoAxesCombined,
    FileUp,
    SearchCheck,
    ShieldCheck,
    Sparkles,
    UsersRound,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, login, register } from '@/routes';
import type { Auth } from '@/types';

const features = [
    {
        icon: FileUp,
        title: 'Import with confidence',
        description:
            'Upload CSV files, map columns, and preserve every submitted row.',
    },
    {
        icon: SearchCheck,
        title: 'Validate every lead',
        description:
            'Catch incomplete records before they reach your working database.',
    },
    {
        icon: UsersRound,
        title: 'Built for teams',
        description:
            'Give administrators and agents exactly the access they need.',
    },
];

type Overview = {
    stats: {
        total: number;
        total_change: number | null;
        validated: number;
        validated_rate: number | null;
        this_month: number;
        this_month_change: number | null;
    };
    growth: { week_start: string; leads: number }[];
    activity: {
        type: 'batch' | 'batch_failed' | 'lead' | 'verified';
        label: string;
        occurred_at: string;
    }[];
};

const minutesAgo = (minutes: number) =>
    new Date(Date.now() - minutes * 60_000).toISOString();

/** Illustrative figures shown to guests, who cannot see real lead data. */
const sampleOverview: Overview = {
    stats: {
        total: 18642,
        total_change: 12.4,
        validated: 16208,
        validated_rate: 86.9,
        this_month: 2481,
        this_month_change: 18.1,
    },
    growth: [30, 45, 38, 62, 55, 78, 69, 94].map((leads, index) => ({
        week_start: String(index),
        leads,
    })),
    activity: [
        {
            type: 'batch',
            label: 'CSV batch accepted',
            occurred_at: minutesAgo(2),
        },
        { type: 'lead', label: 'New lead added', occurred_at: minutesAgo(3) },
        {
            type: 'verified',
            label: 'Lead verified',
            occurred_at: minutesAgo(4),
        },
    ],
};

const activityDotColors: Record<Overview['activity'][number]['type'], string> =
    {
        batch: 'bg-cyan-300',
        batch_failed: 'bg-rose-400',
        lead: 'bg-indigo-400',
        verified: 'bg-emerald-400',
    };

const formatChange = (change: number | null) =>
    change === null ? 'No prior data' : `${change > 0 ? '+' : ''}${change}%`;

const relativeTime = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

function timeAgo(timestamp: string): string {
    const seconds = Math.round(
        (new Date(timestamp).getTime() - Date.now()) / 1000,
    );
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relativeTime.format(Math.round(seconds / size), unit);
        }
    }

    return 'just now';
}

export default function Welcome() {
    const { auth, overview: liveOverview } = usePage<{
        auth: Auth;
        overview: Overview | null;
    }>().props;
    const isLive = liveOverview !== null;
    const overview = liveOverview ?? sampleOverview;
    const peakWeek = Math.max(...overview.growth.map(({ leads }) => leads), 1);

    usePoll(15000, { only: ['overview'] }, { autoStart: isLive });

    return (
        <>
            <Head title="Lead intelligence, organized" />
            <div className="min-h-screen overflow-hidden bg-[#07111f] text-white selection:bg-cyan-300 selection:text-slate-950">
                <div className="pointer-events-none absolute inset-0 [background-image:linear-gradient(rgba(148,163,184,.12)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,.12)_1px,transparent_1px)] [background-size:54px_54px] opacity-20" />

                <header className="relative z-10 mx-auto flex max-w-7xl items-center justify-between px-5 py-6 lg:px-8">
                    <Link href="/" className="flex items-center gap-3">
                        <AppLogoIcon className="size-10 object-contain" />
                        <span className="text-lg font-semibold tracking-tight">
                            LeadGen{' '}
                            <span className="text-cyan-300">Central</span>
                        </span>
                    </Link>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-flex h-10 items-center gap-2 rounded-xl bg-white px-5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-100"
                            >
                                Open dashboard <ArrowRight className="size-4" />
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="rounded-xl px-4 py-2 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="rounded-xl border border-cyan-300/30 bg-cyan-300/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:bg-cyan-300 hover:text-slate-950"
                                >
                                    Create account
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                <main className="relative z-10">
                    <section className="mx-auto grid max-w-7xl items-center gap-14 px-5 pt-16 pb-20 lg:grid-cols-[1.02fr_.98fr] lg:px-8 lg:pt-24 lg:pb-28">
                        <div>
                            <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-cyan-300/20 bg-cyan-300/8 px-3 py-1.5 text-xs font-medium text-cyan-200">
                                <Sparkles className="size-3.5" /> One workspace
                                for your entire lead operation
                            </div>
                            <h1 className="max-w-3xl text-5xl leading-[1.04] font-semibold tracking-[-0.045em] sm:text-6xl lg:text-7xl">
                                Turn scattered data into{' '}
                                <span className="text-cyan-300">
                                    qualified opportunities.
                                </span>
                            </h1>
                            <p className="mt-7 max-w-xl text-lg leading-8 text-slate-300">
                                Import, validate, organize, and manage company
                                leads without losing visibility. LeadGen Central
                                gives your team a reliable source of truth from
                                the first upload.
                            </p>
                            <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                                <Link
                                    href={auth.user ? dashboard() : register()}
                                    className="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-cyan-300 px-6 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200"
                                >
                                    {auth.user
                                        ? 'Go to dashboard'
                                        : 'Start organizing leads'}{' '}
                                    <ArrowRight className="size-4" />
                                </Link>
                                <Link
                                    href={login()}
                                    className="inline-flex h-12 items-center justify-center rounded-xl border border-white/15 bg-white/5 px-6 text-sm font-semibold text-white transition hover:bg-white/10"
                                >
                                    Sign in to your workspace
                                </Link>
                            </div>
                            <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-slate-400">
                                {[
                                    'Role-based access',
                                    'Row-level validation',
                                    'Secure ownership',
                                ].map((item) => (
                                    <span
                                        key={item}
                                        className="flex items-center gap-2"
                                    >
                                        <BadgeCheck className="size-4 text-cyan-300" />
                                        {item}
                                    </span>
                                ))}
                            </div>
                        </div>

                        <div className="relative mx-auto w-full max-w-2xl">
                            <div className="absolute -inset-8 rounded-full bg-cyan-400/10 blur-3xl" />
                            <div className="relative overflow-hidden rounded-3xl border border-white/10 bg-slate-900/80 p-3 backdrop-blur-xl">
                                <div className="flex items-center justify-between border-b border-white/8 px-4 py-3">
                                    <div className="flex gap-1.5">
                                        <span className="size-2.5 rounded-full bg-rose-400" />
                                        <span className="size-2.5 rounded-full bg-amber-300" />
                                        <span className="size-2.5 rounded-full bg-emerald-400" />
                                    </div>
                                    <span className="text-xs text-slate-500">
                                        Lead intelligence overview
                                    </span>
                                    {isLive ? (
                                        <span className="flex items-center gap-1.5 text-[10px] font-semibold tracking-wider text-emerald-300 uppercase">
                                            <span className="size-1.5 animate-pulse rounded-full bg-emerald-400" />
                                            Live
                                        </span>
                                    ) : (
                                        <ShieldCheck className="size-4 text-cyan-300" />
                                    )}
                                </div>
                                <div className="grid gap-3 p-3 sm:grid-cols-3">
                                    {[
                                        [
                                            'Total leads',
                                            overview.stats.total.toLocaleString(),
                                            formatChange(
                                                overview.stats.total_change,
                                            ),
                                        ],
                                        [
                                            'Validated',
                                            overview.stats.validated.toLocaleString(),
                                            overview.stats.validated_rate ===
                                            null
                                                ? '—'
                                                : `${overview.stats.validated_rate}%`,
                                        ],
                                        [
                                            'This month',
                                            overview.stats.this_month.toLocaleString(),
                                            formatChange(
                                                overview.stats
                                                    .this_month_change,
                                            ),
                                        ],
                                    ].map(([label, value, trend]) => (
                                        <div
                                            key={label}
                                            className="rounded-2xl border border-white/8 bg-white/[.035] p-4"
                                        >
                                            <p className="text-xs text-slate-400">
                                                {label}
                                            </p>
                                            <p className="mt-2 text-2xl font-semibold">
                                                {value}
                                            </p>
                                            <p className="mt-1 text-xs text-cyan-300">
                                                {trend}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                                <div className="grid gap-3 p-3 pt-0 sm:grid-cols-[1.4fr_.6fr]">
                                    <div className="rounded-2xl border border-white/8 bg-white/[.035] p-5">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-sm font-medium">
                                                    Lead growth
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    Last 8 weeks
                                                </p>
                                            </div>
                                            <ChartNoAxesCombined className="size-5 text-cyan-300" />
                                        </div>
                                        <div className="mt-8 flex h-40 items-end gap-2">
                                            {overview.growth.map(
                                                ({ week_start, leads }) => (
                                                    <div
                                                        key={week_start}
                                                        title={
                                                            isLive
                                                                ? `Week of ${week_start}: ${leads.toLocaleString()} leads`
                                                                : undefined
                                                        }
                                                        className="flex-1 rounded-t-md bg-cyan-300 transition-[height] duration-500"
                                                        style={{
                                                            height: `${Math.max((leads / peakWeek) * 100, 3)}%`,
                                                        }}
                                                    />
                                                ),
                                            )}
                                        </div>
                                    </div>
                                    <div className="rounded-2xl border border-white/8 bg-white/[.035] p-5">
                                        <p className="text-sm font-medium">
                                            Recent activity
                                        </p>
                                        <div className="mt-5 flex flex-col gap-5">
                                            {overview.activity.length === 0 && (
                                                <p className="text-xs text-slate-500">
                                                    No activity yet.
                                                </p>
                                            )}
                                            {overview.activity.map(
                                                (event, index) => (
                                                    <div
                                                        key={`${event.type}-${event.occurred_at}-${index}`}
                                                        className="flex gap-3"
                                                    >
                                                        <span
                                                            className={`mt-1 size-2 shrink-0 rounded-full ${activityDotColors[event.type]}`}
                                                        />
                                                        <div>
                                                            <p className="text-xs text-slate-200">
                                                                {event.label}
                                                            </p>
                                                            <p className="text-[10px] text-slate-500">
                                                                {timeAgo(
                                                                    event.occurred_at,
                                                                )}
                                                            </p>
                                                        </div>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="border-y border-white/8 bg-white/[.025]">
                        <div className="mx-auto grid max-w-7xl gap-5 px-5 py-16 md:grid-cols-3 lg:px-8">
                            {features.map(
                                ({ icon: Icon, title, description }) => (
                                    <article
                                        key={title}
                                        className="rounded-2xl border border-white/8 bg-slate-900/50 p-6"
                                    >
                                        <span className="flex size-11 items-center justify-center rounded-xl bg-cyan-300/10 text-cyan-300">
                                            <Icon className="size-5" />
                                        </span>
                                        <h2 className="mt-5 text-lg font-semibold">
                                            {title}
                                        </h2>
                                        <p className="mt-2 text-sm leading-6 text-slate-400">
                                            {description}
                                        </p>
                                    </article>
                                ),
                            )}
                        </div>
                    </section>
                </main>
                <footer className="relative z-10 mx-auto flex max-w-7xl flex-col justify-between gap-3 px-5 py-8 text-xs text-slate-500 sm:flex-row lg:px-8">
                    <span>© {new Date().getFullYear()} LeadGen Central</span>
                    <span>
                        Powered by:{' '}
                        <span className="font-medium text-slate-300">
                            EBNLeadgen
                        </span>{' '}
                        · Developed by:{' '}
                        <span className="font-medium text-slate-300">
                            Elmar Noche &amp; Jonathan Quiles
                        </span>
                    </span>
                    <span>
                        Lead generation, validation, and intelligence
                        management.
                    </span>
                </footer>
            </div>
        </>
    );
}

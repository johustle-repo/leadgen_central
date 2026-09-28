import { Link } from '@inertiajs/react';
import { ArrowDownRight, ArrowRight, ArrowUpRight } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    Cell,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    ChartTooltip,
    formatLabel,
    percent,
} from '@/components/database-charts';

/**
 * Power BI-style report building blocks for the Dashboard: every visual sits in
 * a compact tile with a small header, values are always printed as text next to
 * their marks (never tooltip-only), and categorical hues come from the fixed
 * `--chart-N` order so an entity keeps its color regardless of rank.
 */
export const CATEGORICAL = [
    'var(--color-chart-1)',
    'var(--color-chart-2)',
    'var(--color-chart-3)',
    'var(--color-chart-4)',
    'var(--color-chart-5)',
] as const;

export function Visual({
    title,
    subtitle,
    action,
    children,
    className = '',
}: {
    title: string;
    subtitle?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            aria-label={title}
            className={`flex min-w-0 flex-col rounded-lg border bg-card p-4 shadow-xs ${className}`}
        >
            <header className="mb-3 flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="text-sm font-semibold text-foreground">
                        {title}
                    </h2>
                    {subtitle && (
                        <p className="text-xs text-muted-foreground">
                            {subtitle}
                        </p>
                    )}
                </div>
                {action}
            </header>
            <div className="flex min-w-0 flex-1 flex-col">{children}</div>
        </section>
    );
}

/**
 * Period-over-period delta. Direction is carried by an arrow icon and a sign,
 * not by color alone.
 */
function Delta({ change }: { change: number | null }) {
    if (change === null) {
        return <span className="text-muted-foreground">No prior data</span>;
    }

    const Icon =
        change > 0 ? ArrowUpRight : change < 0 ? ArrowDownRight : ArrowRight;
    const tone =
        change > 0
            ? 'text-success'
            : change < 0
              ? 'text-destructive'
              : 'text-muted-foreground';

    return (
        <span
            className={`inline-flex items-center gap-0.5 font-medium ${tone}`}
        >
            <Icon className="size-3.5" aria-hidden="true" />
            {change > 0 ? '+' : ''}
            {change}%
            <span className="ml-1 font-normal text-muted-foreground">
                vs prev.
            </span>
        </span>
    );
}

export function KpiCard({
    label,
    value,
    change,
    footnote,
    icon: Icon,
    trend,
    trendLabel,
}: {
    label: string;
    value: string | number;
    change?: number | null;
    footnote?: string;
    icon: LucideIcon;
    trend?: Array<{ date: string; value: number }>;
    trendLabel?: string;
}) {
    return (
        <div className="flex min-w-0 flex-col rounded-lg border bg-card p-4 shadow-xs">
            <div className="flex items-center justify-between gap-2">
                <p className="truncate text-xs font-medium text-muted-foreground">
                    {label}
                </p>
                <Icon
                    className="size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>
            <p className="mt-2 text-3xl font-semibold tracking-tight text-foreground">
                {typeof value === 'number' ? value.toLocaleString() : value}
            </p>
            <div className="mt-1 min-h-4 text-xs">
                {change !== undefined && <Delta change={change} />}
            </div>
            {footnote && (
                <p className="mt-0.5 text-xs text-muted-foreground">
                    {footnote}
                </p>
            )}
            {trend && trend.length > 1 && (
                <div className="mt-3 h-10" aria-hidden="true">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart
                            data={trend}
                            margin={{ top: 2, right: 0, left: 0, bottom: 0 }}
                        >
                            <Tooltip
                                content={ChartTooltip}
                                cursor={{ stroke: 'var(--border)' }}
                            />
                            <Area
                                type="monotone"
                                dataKey="value"
                                name={trendLabel ?? label}
                                stroke="var(--color-chart-1)"
                                strokeWidth={2}
                                fill="var(--color-chart-1)"
                                fillOpacity={0.12}
                                isAnimationActive={false}
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
            )}
        </div>
    );
}

/**
 * Horizontal bar list scaled to the largest row, with the value printed at the
 * end of every bar - the Power BI "clustered bar with data labels" look. Rows
 * with an href open the records behind that bar.
 */
export function BarList({
    rows,
    empty = 'No records in this period.',
    uppercase = false,
    format = (value: number) => value.toLocaleString(),
    scaleMax,
}: {
    rows: Array<{
        label: string;
        value: number;
        percent?: number | null;
        href?: string;
    }>;
    empty?: string;
    uppercase?: boolean;
    format?: (value: number) => string;
    /** Fixed axis maximum (e.g. 100 for rates); defaults to the largest row. */
    scaleMax?: number;
}) {
    if (rows.length === 0) {
        return <p className="py-6 text-sm text-muted-foreground">{empty}</p>;
    }

    const max = scaleMax ?? Math.max(...rows.map((row) => row.value), 1);

    return (
        <ul className="flex flex-col gap-2.5">
            {rows.map((row, index) => {
                const label = uppercase ? row.label : formatLabel(row.label);
                const content = (
                    <>
                        <span
                            className={`truncate text-muted-foreground ${uppercase ? 'uppercase' : ''} ${row.href ? 'group-hover:text-foreground group-hover:underline' : ''}`}
                        >
                            {label}
                        </span>
                        <span className="flex min-w-0 items-center gap-2">
                            <span
                                className="h-4 rounded-r-[4px] bg-chart-1 transition-opacity group-hover:opacity-80"
                                style={{
                                    width: `${Math.max(2, (row.value / max) * 100)}%`,
                                }}
                                aria-hidden="true"
                            />
                            <span className="shrink-0 font-medium text-foreground tabular-nums">
                                {format(row.value)}
                                {row.percent !== undefined &&
                                    row.percent !== null && (
                                        <span className="ml-1 font-normal text-muted-foreground">
                                            {percent(row.percent)}
                                        </span>
                                    )}
                            </span>
                        </span>
                    </>
                );
                const rowClassName =
                    'group grid grid-cols-[minmax(0,7.5rem)_1fr] items-center gap-3 text-xs';

                return (
                    <li key={`${row.label}-${index}`}>
                        {row.href ? (
                            <Link
                                href={row.href}
                                className={`${rowClassName} -mx-1.5 rounded-md px-1.5 py-0.5 transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none`}
                                title={`View the ${format(row.value)} leads in ${label}`}
                            >
                                {content}
                            </Link>
                        ) : (
                            <div
                                className={rowClassName}
                                title={`${label}: ${format(row.value)}`}
                            >
                                {content}
                            </div>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

export function Donut({
    rows,
    centerLabel,
    colors,
}: {
    rows: Array<{ label: string; value: number; percent: number | null }>;
    centerLabel: string;
    /** Fixed label -> color map so a category keeps its hue whatever its rank. */
    colors: Record<string, string>;
}) {
    const colorOf = (label: string) =>
        colors[label] ?? 'var(--muted-foreground)';
    const total = rows.reduce((sum, row) => sum + row.value, 0);

    if (total === 0) {
        return (
            <p className="py-6 text-sm text-muted-foreground">
                No records in this period.
            </p>
        );
    }

    return (
        <div className="flex flex-1 flex-col items-center justify-center gap-4">
            <div className="relative h-44 w-44">
                <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                        <Tooltip content={ChartTooltip} />
                        <Pie
                            data={rows.map((row) => ({
                                ...row,
                                name: formatLabel(row.label),
                            }))}
                            dataKey="value"
                            nameKey="name"
                            innerRadius="68%"
                            outerRadius="100%"
                            stroke="var(--card)"
                            strokeWidth={2}
                            isAnimationActive={false}
                        >
                            {rows.map((row) => (
                                <Cell
                                    key={row.label}
                                    fill={colorOf(row.label)}
                                />
                            ))}
                        </Pie>
                    </PieChart>
                </ResponsiveContainer>
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span className="text-2xl font-semibold text-foreground">
                        {total.toLocaleString()}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {centerLabel}
                    </span>
                </div>
            </div>
            <ul className="w-full space-y-1.5 text-xs">
                {rows.map((row) => (
                    <li
                        key={row.label}
                        className="flex items-center justify-between gap-3"
                    >
                        <span className="flex min-w-0 items-center gap-2 text-muted-foreground">
                            <span
                                className="size-2 shrink-0 rounded-[2px]"
                                style={{ backgroundColor: colorOf(row.label) }}
                            />
                            <span className="truncate">
                                {formatLabel(row.label)}
                            </span>
                        </span>
                        <span className="shrink-0 font-medium text-foreground tabular-nums">
                            {row.value.toLocaleString()}
                            <span className="ml-1 font-normal text-muted-foreground">
                                {percent(row.percent)}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function TrendArea({
    points,
    seriesLabel,
}: {
    points: Array<{ date: string; value: number }>;
    seriesLabel: string;
}) {
    return (
        <>
            <div
                className="h-64 min-w-0"
                role="img"
                aria-label={`${seriesLabel} per interval. Exact values are in the data table below.`}
            >
                <ResponsiveContainer width="100%" height="100%">
                    <AreaChart
                        data={points}
                        margin={{ top: 8, right: 8, left: 0, bottom: 0 }}
                        accessibilityLayer
                    >
                        <defs>
                            <linearGradient
                                id="trend-fill"
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1"
                            >
                                <stop
                                    offset="0%"
                                    stopColor="var(--color-chart-1)"
                                    stopOpacity={0.25}
                                />
                                <stop
                                    offset="100%"
                                    stopColor="var(--color-chart-1)"
                                    stopOpacity={0.02}
                                />
                            </linearGradient>
                        </defs>
                        <CartesianGrid
                            stroke="var(--border)"
                            vertical={false}
                        />
                        <XAxis
                            dataKey="date"
                            tick={{
                                fill: 'var(--muted-foreground)',
                                fontSize: 11,
                            }}
                            tickLine={false}
                            axisLine={{ stroke: 'var(--border)' }}
                            minTickGap={40}
                        />
                        <YAxis
                            allowDecimals={false}
                            tick={{
                                fill: 'var(--muted-foreground)',
                                fontSize: 11,
                            }}
                            tickLine={false}
                            axisLine={false}
                            width={40}
                        />
                        <Tooltip
                            content={ChartTooltip}
                            cursor={{ stroke: 'var(--muted-foreground)' }}
                        />
                        <Area
                            type="monotone"
                            dataKey="value"
                            name={seriesLabel}
                            stroke="var(--color-chart-1)"
                            strokeWidth={2}
                            fill="url(#trend-fill)"
                            activeDot={{
                                r: 4,
                                strokeWidth: 2,
                                stroke: 'var(--card)',
                            }}
                            isAnimationActive={false}
                        />
                    </AreaChart>
                </ResponsiveContainer>
            </div>
            <details className="mt-2 text-xs">
                <summary className="cursor-pointer text-muted-foreground">
                    View data
                </summary>
                <div className="mt-2 max-h-48 overflow-auto">
                    <table className="w-full text-left">
                        <thead>
                            <tr>
                                <th className="p-1.5">Interval starts</th>
                                <th className="p-1.5 text-right">
                                    {seriesLabel}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {points.map((point) => (
                                <tr key={point.date} className="border-t">
                                    <td className="p-1.5">{point.date}</td>
                                    <td className="p-1.5 text-right tabular-nums">
                                        {point.value.toLocaleString()}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </details>
        </>
    );
}

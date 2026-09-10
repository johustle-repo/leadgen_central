import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import type { TooltipContentProps } from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type {
    DatabaseAnalytics,
    Distribution,
} from '@/types/database-analytics';

/**
 * Shared tooltip for the bar charts below: the value leads (bold, high-contrast)
 * and the series name follows (muted), keyed by a small rect swatch rather than
 * coloring the text itself - identity lives in the mark, not the label.
 */
export function ChartTooltip({ active, label, payload }: TooltipContentProps) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="rounded-lg border bg-card px-3 py-2 text-xs shadow-md">
            <p className="mb-1.5 font-medium text-foreground">{label}</p>
            <div className="flex flex-col gap-1">
                {payload.map((entry, index) => (
                    <div
                        key={String(entry.dataKey ?? entry.name ?? index)}
                        className="flex items-center gap-2"
                    >
                        <span
                            className="size-2 shrink-0 rounded-[2px]"
                            style={{ backgroundColor: entry.color }}
                        />
                        <span className="font-semibold tabular-nums text-foreground">
                            {typeof entry.value === 'number'
                                ? entry.value.toLocaleString()
                                : entry.value}
                        </span>
                        <span className="text-muted-foreground">
                            {entry.name}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

/**
 * Legend row for the bar charts below: a small rect swatch carries identity, the
 * label stays in muted text - never colored text standing in for a legend box.
 */
export function ChartLegend({
    items,
}: {
    items: Array<{ key: string; label: string; color: string }>;
}) {
    return (
        <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-xs">
            {items.map((item) => (
                <span
                    key={item.key}
                    className="flex items-center gap-1.5 text-muted-foreground"
                >
                    <span
                        className="size-2 shrink-0 rounded-[2px]"
                        style={{ backgroundColor: item.color }}
                    />
                    {item.label}
                </span>
            ))}
        </div>
    );
}

export const formatLabel = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
export const percent = (value: number | null) =>
    value === null ? 'N/A' : `${value.toLocaleString()}%`;
export const changeLabel = (value: number | null) =>
    value === null
        ? 'New · no prior baseline'
        : `${value > 0 ? '+' : ''}${value}% vs previous period`;

/**
 * One or two plain-language sentences answering "what happened, and is it good" from
 * the same overview/changes/quality figures already shown in the sections below -
 * meant to be readable without opening any panel. Used at the top of both the
 * Dashboard and Reports pages, which share this exact data shape.
 */
const count = (value: number, singular: string, plural: string) =>
    `${value.toLocaleString()} ${value === 1 ? singular : plural}`;

export function summarize({
    overview,
    changes,
    quality,
}: Pick<DatabaseAnalytics, 'overview' | 'changes' | 'quality'>): string {
    const change =
        changes.records === null
            ? ''
            : changes.records === 0
              ? ', level with the previous period'
              : `, ${changes.records > 0 ? 'up' : 'down'} ${Math.abs(changes.records)}% from the previous period`;
    const sentences = [
        `${count(overview.records, 'record', 'records')} added${change}.`,
    ];

    if (quality.accepted_rate !== null) {
        sentences.push(`${quality.accepted_rate}% came through clean.`);
    }

    if (overview.companies > 0 || overview.countries > 0) {
        sentences.push(
            `${count(overview.companies, 'company', 'companies')} across ${count(overview.countries, 'country', 'countries')}.`,
        );
    }

    return sentences.join(' ');
}

export function DistributionChart({
    title,
    rows,
    description,
}: {
    title: string;
    rows: Distribution[];
    description?: string;
}) {
    return (
        <Card className="min-w-0">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description && (
                    <p className="text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <p className="py-6 text-sm text-muted-foreground">
                        No records in this period.
                    </p>
                ) : (
                    <ul className="space-y-3" aria-label={title}>
                        {rows.map((row, index) => (
                            <li key={`${row.label}-${index}`}>
                                <div className="mb-1 flex items-baseline justify-between gap-3 text-xs">
                                    <span className="min-w-0 break-words">
                                        {formatLabel(row.label)}
                                    </span>
                                    <span className="shrink-0 tabular-nums">
                                        {row.value.toLocaleString()}{' '}
                                        <span className="text-muted-foreground">
                                            · {percent(row.percent)}
                                        </span>
                                    </span>
                                </div>
                                <div
                                    className="h-1.5 overflow-hidden rounded-full bg-muted"
                                    aria-hidden="true"
                                >
                                    <div
                                        className="h-full rounded-full bg-chart-1"
                                        style={{
                                            width: `${Math.min(100, row.percent ?? 0)}%`,
                                        }}
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

const GROWTH_SERIES = [
    { key: 'records', label: 'Records added', color: 'var(--color-chart-1)' },
    {
        key: 'companies',
        label: 'First-seen companies',
        color: 'var(--color-chart-2)',
    },
    {
        key: 'emails',
        label: 'First-seen emails',
        color: 'var(--color-chart-4)',
    },
] as const;

export function GrowthChart({
    growth,
}: {
    growth: DatabaseAnalytics['growth'];
}) {
    return (
        <>
            <div
                className="h-72 min-w-0"
                role="img"
                aria-label="Records, first-seen companies and first-seen email addresses added by reporting interval, one bar group per interval. Exact values are in the expandable data table below."
            >
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart
                        data={growth.points}
                        margin={{ top: 12, right: 12, left: 0, bottom: 8 }}
                        barGap={2}
                        barCategoryGap="20%"
                        accessibilityLayer
                    >
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
                            minTickGap={40}
                        />
                        <YAxis
                            allowDecimals={false}
                            tick={{
                                fill: 'var(--muted-foreground)',
                                fontSize: 11,
                            }}
                            width={45}
                        />
                        <Tooltip
                            content={ChartTooltip}
                            cursor={{ fill: 'var(--muted)', opacity: 0.4 }}
                        />
                        {GROWTH_SERIES.map((series) => (
                            <Bar
                                key={series.key}
                                dataKey={series.key}
                                name={series.label}
                                fill={series.color}
                                maxBarSize={20}
                                radius={[4, 4, 0, 0]}
                                isAnimationActive={false}
                            />
                        ))}
                    </BarChart>
                </ResponsiveContainer>
            </div>
            <ChartLegend
                items={GROWTH_SERIES.map((series) => ({
                    key: series.key,
                    label: series.label,
                    color: series.color,
                }))}
            />
            <details className="mt-4 text-xs">
                <summary className="cursor-pointer text-muted-foreground">
                    View growth data
                </summary>
                <div className="mt-3 max-h-64 overflow-auto">
                    <table className="w-full text-left">
                        <caption className="sr-only">
                            Growth by interval
                        </caption>
                        <thead>
                            <tr>
                                {[
                                    'Interval starts',
                                    'Records',
                                    'Companies',
                                    'Emails',
                                ].map((label) => (
                                    <th key={label} className="p-2">
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {growth.points.map((point) => (
                                <tr key={point.date} className="border-t">
                                    <td className="p-2">{point.date}</td>
                                    <td className="p-2">{point.records}</td>
                                    <td className="p-2">{point.companies}</td>
                                    <td className="p-2">{point.emails}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </details>
        </>
    );
}

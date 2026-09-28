import { Link } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Building2,
    ChevronDown,
    ChevronRight,
    Globe2,
    List,
    MapPin,
    Sparkles,
    Target,
    Users,
    X,
} from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import {
    Bar,
    BarChart,
    Cell,
    LabelList,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { KpiCard, Visual } from '@/components/bi-visuals';
import {
    ChartLegend,
    ChartTooltip,
    percent,
} from '@/components/database-charts';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { COUNTRY_CAPITALS } from '@/lib/country-capitals';
import { leadDrilldownUrl } from '@/lib/lead-drilldown';
import type { LeadDrilldown } from '@/lib/lead-drilldown';
import type {
    DemographicMetrics,
    DemographicRow,
    Demographics,
} from '@/types/database-report';

type Dimension = 'region' | 'country' | 'agent' | 'city';
type Filters = Partial<Record<Dimension, string>>;
type Measure = 'records' | 'possible';
type Group = DemographicMetrics & { key: string; label: string };
type SortKey = keyof DemographicMetrics | 'rate' | 'label';

const ALL = '__all__';
/** Every bar visual reserves this many rows so tiles line up at one height. */
const CHART_ROWS = 10;
const ROW_HEIGHT = 40;
const SERIES = [
    { key: 'records', label: 'Leads', color: 'var(--color-chart-1)' },
    { key: 'possible', label: 'Possible leads', color: 'var(--color-chart-2)' },
] as const;
const METRICS = ['records', 'possible', 'qualified', 'forwarded'] as const;
const displayNames = new Intl.DisplayNames(['en'], { type: 'region' });

export function countryLabel(code: string): string {
    if (/^[A-Z]{2}$/.test(code)) {
        return COUNTRY_CAPITALS[code]?.name ?? displayNames.of(code) ?? code;
    }

    return code.replace(/\b\w/g, (letter) => letter.toUpperCase());
}

const fold = (value: string) =>
    value.normalize('NFD').replace(/[̀-ͯ]/g, '').trim().toLowerCase();

function isCapital(country: string, city: string): boolean {
    const capital = COUNTRY_CAPITALS[country]?.capital;

    return capital !== undefined && fold(capital) === fold(city);
}

const KEY_OF: Record<Dimension, (row: DemographicRow) => string> = {
    region: (row) => row.region,
    country: (row) => row.country,
    agent: (row) => (row.agent_id === null ? 'none' : String(row.agent_id)),
    city: (row) => `${row.country}|${fold(row.city)}`,
};

/** Clearing a broader slicer also clears the narrower ones inside it. */
const NARROWER: Record<Dimension, Dimension[]> = {
    region: ['country', 'city'],
    country: ['city'],
    city: [],
    agent: [],
};

function matches(row: DemographicRow, filters: Filters, except?: Dimension) {
    return (Object.keys(filters) as Dimension[]).every(
        (dimension) =>
            dimension === except ||
            filters[dimension] === undefined ||
            KEY_OF[dimension](row) === filters[dimension],
    );
}

function emptyMetrics(): DemographicMetrics {
    return { records: 0, possible: 0, qualified: 0, forwarded: 0 };
}

function addMetrics(target: DemographicMetrics, row: DemographicMetrics) {
    for (const metric of METRICS) {
        target[metric] += row[metric];
    }
}

function group(
    rows: DemographicRow[],
    dimension: Dimension,
    labelOf: (row: DemographicRow) => string,
): Group[] {
    const groups = new Map<string, Group>();

    for (const row of rows) {
        const key = KEY_OF[dimension](row);
        const existing = groups.get(key) ?? {
            key,
            label: labelOf(row),
            ...emptyMetrics(),
        };
        addMetrics(existing, row);
        groups.set(key, existing);
    }

    return [...groups.values()];
}

const possibleRate = (metrics: DemographicMetrics) =>
    metrics.records > 0
        ? Math.round((1000 * metrics.possible) / metrics.records) / 10
        : null;

function ClusteredBars({
    groups,
    measure,
    selected,
    onSelect,
    limit = CHART_ROWS,
    empty = 'No leads match the current filters.',
}: {
    groups: Group[];
    measure: Measure;
    selected?: string;
    onSelect: (key: string) => void;
    limit?: number;
    empty?: string;
}) {
    const data = [...groups]
        .sort(
            (a, b) =>
                b[measure] - a[measure] ||
                b.records - a.records ||
                a.label.localeCompare(b.label),
        )
        .slice(0, limit);

    return (
        <div className="flex flex-1 flex-col">
            <div
                className="min-w-0"
                style={{ height: CHART_ROWS * ROW_HEIGHT + 8 }}
            >
                {data.length === 0 ? (
                    <p className="py-6 text-sm text-muted-foreground">
                        {empty}
                    </p>
                ) : (
                    <div
                        className="min-w-0"
                        style={{ height: data.length * ROW_HEIGHT + 8 }}
                        role="img"
                        aria-label={`Leads and possible leads for ${data.map((row) => `${row.label}: ${row.records} leads, ${row.possible} possible`).join('; ')}`}
                    >
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart
                                layout="vertical"
                                data={data}
                                margin={{
                                    top: 0,
                                    right: 40,
                                    left: 0,
                                    bottom: 0,
                                }}
                                barGap={2}
                                barCategoryGap={8}
                            >
                                <XAxis type="number" hide />
                                <YAxis
                                    type="category"
                                    dataKey="label"
                                    width={156}
                                    interval={0}
                                    tickLine={false}
                                    axisLine={false}
                                    tick={{
                                        fill: 'var(--muted-foreground)',
                                        fontSize: 11,
                                    }}
                                    tickFormatter={(value: string) =>
                                        value.length > 20
                                            ? `${value.slice(0, 19)}…`
                                            : value
                                    }
                                />
                                <Tooltip
                                    content={ChartTooltip}
                                    cursor={{
                                        fill: 'var(--muted)',
                                        opacity: 0.5,
                                    }}
                                />
                                {SERIES.map((series) => (
                                    <Bar
                                        key={series.key}
                                        dataKey={series.key}
                                        name={series.label}
                                        fill={series.color}
                                        maxBarSize={14}
                                        radius={[0, 4, 4, 0]}
                                        cursor="pointer"
                                        isAnimationActive={false}
                                        onClick={(_, index) =>
                                            onSelect(data[index].key)
                                        }
                                    >
                                        {data.map((row) => (
                                            <Cell
                                                key={row.key}
                                                fillOpacity={
                                                    selected &&
                                                    selected !== row.key
                                                        ? 0.25
                                                        : 1
                                                }
                                            />
                                        ))}
                                        <LabelList
                                            dataKey={series.key}
                                            position="right"
                                            fill="var(--foreground)"
                                            fontSize={11}
                                            formatter={(value) =>
                                                Number(value).toLocaleString()
                                            }
                                        />
                                    </Bar>
                                ))}
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                )}
            </div>
            <ChartLegend
                items={SERIES.map((series) => ({
                    key: series.key,
                    label: series.label,
                    color: series.color,
                }))}
            />
            <p className="mt-2 text-xs text-muted-foreground">
                {groups.length > limit
                    ? `Top ${limit} of ${groups.length.toLocaleString()} · `
                    : ''}
                Click a bar to filter the whole page
            </p>
        </div>
    );
}

type MatrixNode = Group & {
    children: MatrixNode[];
    /** Lead list filters for the leads in this row. */
    drilldown: LeadDrilldown;
};

function buildTree(rows: DemographicRow[]): MatrixNode[] {
    const regions = new Map<string, MatrixNode>();

    for (const row of rows) {
        const region = regions.get(row.region) ?? {
            key: row.region,
            label: row.region,
            children: [],
            drilldown: { region: row.region },
            ...emptyMetrics(),
        };
        regions.set(row.region, region);
        let country = region.children.find(
            (child) => child.key === `${row.region}|${row.country}`,
        );

        if (!country) {
            country = {
                key: `${row.region}|${row.country}`,
                label: countryLabel(row.country),
                children: [],
                drilldown: { country_group: row.country },
                ...emptyMetrics(),
            };
            region.children.push(country);
        }

        const cityKey = `${country.key}|${fold(row.city)}`;
        let city = country.children.find((child) => child.key === cityKey);

        if (!city) {
            city = {
                key: cityKey,
                label: isCapital(row.country, row.city)
                    ? `${row.city} ★`
                    : row.city,
                children: [],
                drilldown: { country_group: row.country, city: row.city },
                ...emptyMetrics(),
            };
            country.children.push(city);
        }

        addMetrics(region, row);
        addMetrics(country, row);
        addMetrics(city, row);
    }

    return [...regions.values()];
}

function DemographicMatrix({
    rows,
    linkFor,
}: {
    rows: DemographicRow[];
    /** Lead list link for a row's leads within the current selection. */
    linkFor: (drilldown: LeadDrilldown) => string | undefined;
}) {
    const [expanded, setExpanded] = useState<Set<string>>(new Set());
    const [sort, setSort] = useState<{ key: SortKey; desc: boolean }>({
        key: 'records',
        desc: true,
    });
    const tree = useMemo(() => buildTree(rows), [rows]);
    const total = useMemo(() => {
        const metrics = emptyMetrics();
        rows.forEach((row) => addMetrics(metrics, row));

        return metrics;
    }, [rows]);
    const valueOf = (node: MatrixNode, key: SortKey) =>
        key === 'label'
            ? node.label
            : key === 'rate'
              ? (possibleRate(node) ?? -1)
              : node[key];
    const sorted = (nodes: MatrixNode[]) =>
        [...nodes].sort((a, b) => {
            const left = valueOf(a, sort.key);
            const right = valueOf(b, sort.key);
            const order =
                typeof left === 'string'
                    ? left.localeCompare(String(right))
                    : left - Number(right);

            return sort.desc ? -order : order;
        });
    const toggle = (key: string) =>
        setExpanded((current) => {
            const next = new Set(current);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });
    const allKeys = tree.flatMap((region) => [
        region.key,
        ...region.children.map((country) => country.key),
    ]);
    const columns: Array<{ key: SortKey; label: string }> = [
        { key: 'label', label: 'Region / Country / City' },
        { key: 'records', label: 'Leads' },
        { key: 'possible', label: 'Possible' },
        { key: 'qualified', label: 'Qualified' },
        { key: 'forwarded', label: 'Forwarded' },
        { key: 'rate', label: 'Possible %' },
    ];

    function renderNode(node: MatrixNode, depth: number): ReactNode {
        const open = expanded.has(node.key);
        const expandable = node.children.length > 0 && depth < 2;
        const share = total.records > 0 ? node.records / total.records : 0;

        return (
            <Fragment key={node.key}>
                <tr
                    className={`hover:bg-muted/50 ${depth === 0 ? 'font-medium' : ''}`}
                >
                    <td className="py-1.5 pr-3">
                        <div
                            className="flex items-center gap-1"
                            style={{ paddingLeft: depth * 20 }}
                        >
                            {expandable ? (
                                <button
                                    type="button"
                                    onClick={() => toggle(node.key)}
                                    aria-expanded={open}
                                    aria-label={`${open ? 'Collapse' : 'Expand'} ${node.label}`}
                                    className="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                >
                                    {open ? (
                                        <ChevronDown className="size-3.5" />
                                    ) : (
                                        <ChevronRight className="size-3.5" />
                                    )}
                                </button>
                            ) : (
                                <span className="w-4.5" />
                            )}
                            <span className="truncate">{node.label}</span>
                            <Link
                                href={linkFor(node.drilldown) ?? '#'}
                                className="ml-1 shrink-0 rounded p-0.5 text-muted-foreground opacity-60 hover:bg-muted hover:text-foreground hover:opacity-100 focus-visible:opacity-100"
                                aria-label={`View the ${node.records.toLocaleString()} leads in ${node.label}`}
                                title="View these leads"
                            >
                                <List className="size-3.5" />
                            </Link>
                        </div>
                    </td>
                    <td className="py-1.5 pr-3 text-right">
                        <div className="relative ml-auto flex h-5 w-28 items-center justify-end">
                            <span
                                className="absolute inset-y-0.5 left-0 rounded-r-[3px] bg-chart-1/20"
                                style={{ width: `${share * 100}%` }}
                                aria-hidden="true"
                            />
                            <span className="relative tabular-nums">
                                {node.records.toLocaleString()}
                            </span>
                        </div>
                    </td>
                    <td className="py-1.5 pr-3 text-right tabular-nums">
                        {node.possible.toLocaleString()}
                    </td>
                    <td className="py-1.5 pr-3 text-right tabular-nums">
                        {node.qualified.toLocaleString()}
                    </td>
                    <td className="py-1.5 pr-3 text-right tabular-nums">
                        {node.forwarded.toLocaleString()}
                    </td>
                    <td className="py-1.5 text-right tabular-nums">
                        {percent(possibleRate(node))}
                    </td>
                </tr>
                {open &&
                    sorted(node.children).map((child) =>
                        renderNode(child, depth + 1),
                    )}
            </Fragment>
        );
    }

    return (
        <>
            <div className="mb-2 flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-7 text-xs"
                    onClick={() => setExpanded(new Set(allKeys))}
                >
                    Expand all
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-7 text-xs"
                    onClick={() => setExpanded(new Set())}
                >
                    Collapse all
                </Button>
            </div>
            <div className="max-h-[32rem] overflow-auto">
                <table className="w-full min-w-[40rem] text-left text-xs">
                    <thead className="sticky top-0 z-10 border-b bg-card text-muted-foreground">
                        <tr>
                            {columns.map((column, index) => (
                                <th
                                    key={column.key}
                                    className={`py-2 font-medium ${index === 0 ? 'pr-3' : 'pr-3 text-right last:pr-0'}`}
                                    aria-sort={
                                        sort.key === column.key
                                            ? sort.desc
                                                ? 'descending'
                                                : 'ascending'
                                            : 'none'
                                    }
                                >
                                    <button
                                        type="button"
                                        className="inline-flex items-center gap-1 hover:text-foreground"
                                        onClick={() =>
                                            setSort((current) => ({
                                                key: column.key,
                                                desc:
                                                    current.key === column.key
                                                        ? !current.desc
                                                        : column.key !==
                                                          'label',
                                            }))
                                        }
                                    >
                                        {column.label}
                                        {sort.key === column.key &&
                                            (sort.desc ? (
                                                <ArrowDown className="size-3" />
                                            ) : (
                                                <ArrowUp className="size-3" />
                                            ))}
                                    </button>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {sorted(tree).map((node) => renderNode(node, 0))}
                        {tree.length === 0 && (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="py-6 text-center text-muted-foreground"
                                >
                                    No leads match the current filters.
                                </td>
                            </tr>
                        )}
                    </tbody>
                    {tree.length > 0 && (
                        <tfoot className="border-t-2 font-semibold">
                            <tr>
                                <td className="py-2 pr-3">Total</td>
                                <td className="py-2 pr-3 text-right tabular-nums">
                                    {total.records.toLocaleString()}
                                </td>
                                <td className="py-2 pr-3 text-right tabular-nums">
                                    {total.possible.toLocaleString()}
                                </td>
                                <td className="py-2 pr-3 text-right tabular-nums">
                                    {total.qualified.toLocaleString()}
                                </td>
                                <td className="py-2 pr-3 text-right tabular-nums">
                                    {total.forwarded.toLocaleString()}
                                </td>
                                <td className="py-2 text-right tabular-nums">
                                    {percent(possibleRate(total))}
                                </td>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
        </>
    );
}

function Slicer({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value?: string;
    options: Array<{ key: string; label: string; records: number }>;
    onChange: (value?: string) => void;
}) {
    return (
        <label className="flex min-w-0 flex-col gap-1 text-xs font-medium text-muted-foreground">
            {label}
            <Select
                value={value ?? ALL}
                onValueChange={(next) =>
                    onChange(next === ALL ? undefined : next)
                }
            >
                <SelectTrigger size="sm" className="w-full min-w-40 bg-card">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>All</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.key} value={option.key}>
                            {option.label} ({option.records.toLocaleString()})
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </label>
    );
}

/**
 * Power BI-style interactive demographics page: slicers across the top, and
 * every visual cross-filters the rest. Each visual ignores its own dimension's
 * filter so the selected bar stays highlighted among its peers instead of
 * becoming the only bar left.
 */
export function LeadDemographics({
    demographics,
    showAgents,
    period,
}: {
    demographics: Demographics;
    showAgents: boolean;
    /** Report period, so lead list links cover the same leads. */
    period: { date_from: string; date_to: string };
}) {
    const [filters, setFilters] = useState<Filters>({});
    const [measure, setMeasure] = useState<Measure>('records');
    const { rows, agents } = demographics;
    const agentLabel = (row: DemographicRow) =>
        row.agent_id === null
            ? 'Unassigned'
            : (agents[row.agent_id] ?? 'Deleted user');
    const cityLabel = (row: DemographicRow) =>
        `${row.city}${isCapital(row.country, row.city) ? ' ★' : ''} · ${row.country}`;

    const select = (dimension: Dimension, key?: string) =>
        setFilters((current) => {
            const next: Filters = { ...current };
            const toggledOff = key === undefined || current[dimension] === key;

            if (toggledOff) {
                delete next[dimension];
            } else {
                next[dimension] = key;
            }

            NARROWER[dimension].forEach((narrower) => delete next[narrower]);

            return next;
        });
    const rowsFor = (except?: Dimension) =>
        rows.filter((row) => matches(row, filters, except));
    const filtered = rowsFor();
    const totals = emptyMetrics();
    filtered.forEach((row) => addMetrics(totals, row));
    const countryCount = new Set(filtered.map((row) => row.country)).size;
    const cityCount = new Set(filtered.map(KEY_OF.city)).size;

    const regionGroups = group(
        rowsFor('region'),
        'region',
        (row) => row.region,
    );
    const countryGroups = group(rowsFor('country'), 'country', (row) =>
        countryLabel(row.country),
    );
    const agentGroups = group(rowsFor('agent'), 'agent', agentLabel);
    const cityGroups = group(rowsFor('city'), 'city', cityLabel);
    const capitalGroups = cityGroups.filter((row) => row.label.includes('★'));
    const labels: Record<Dimension, (key: string) => string> = {
        region: (key) => key,
        country: countryLabel,
        agent: (key) =>
            key === 'none' ? 'Unassigned' : (agents[key] ?? 'Deleted user'),
        city: (key) => key.split('|')[1] ?? key,
    };
    const active = Object.entries(filters) as Array<[Dimension, string]>;
    const selectedCity = filters.city
        ? rows.find((row) => KEY_OF.city(row) === filters.city)
        : undefined;
    const selection: LeadDrilldown = {
        ...(filters.region ? { region: filters.region } : {}),
        ...(filters.country ? { country_group: filters.country } : {}),
        ...(selectedCity
            ? { country_group: selectedCity.country, city: selectedCity.city }
            : {}),
        ...(filters.agent ? { agent: filters.agent } : {}),
    };
    const linkFor = (drilldown: LeadDrilldown = {}) =>
        leadDrilldownUrl(period, '', { ...selection, ...drilldown });
    const bySize = (a: Group, b: Group) =>
        b.records - a.records || a.label.localeCompare(b.label);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-end gap-3 rounded-lg border bg-card p-3 shadow-xs">
                <Slicer
                    label="Region"
                    value={filters.region}
                    options={demographics.regions
                        .map(
                            (region) =>
                                regionGroups.find(
                                    (row) => row.key === region,
                                ) ?? {
                                    key: region,
                                    label: region,
                                    ...emptyMetrics(),
                                },
                        )
                        .filter((row) => row.records > 0)}
                    onChange={(value) => select('region', value)}
                />
                <Slicer
                    label="Country"
                    value={filters.country}
                    options={[...countryGroups].sort(bySize)}
                    onChange={(value) => select('country', value)}
                />
                {showAgents && (
                    <Slicer
                        label="Agent"
                        value={filters.agent}
                        options={[...agentGroups].sort(bySize)}
                        onChange={(value) => select('agent', value)}
                    />
                )}
                <div className="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                    Rank by
                    <div
                        className="inline-flex rounded-md border bg-muted/40 p-0.5"
                        role="radiogroup"
                        aria-label="Rank visuals by"
                    >
                        {SERIES.map((series) => (
                            <button
                                key={series.key}
                                type="button"
                                role="radio"
                                aria-checked={measure === series.key}
                                onClick={() => setMeasure(series.key)}
                                className={`rounded px-3 py-1 text-xs ${measure === series.key ? 'bg-card font-semibold text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}`}
                            >
                                {series.label}
                            </button>
                        ))}
                    </div>
                </div>
                <Button
                    asChild
                    size="sm"
                    variant="outline"
                    className="h-8 text-xs lg:order-last"
                >
                    <Link href={linkFor() ?? '#'}>
                        <List />
                        View {totals.records.toLocaleString()} leads
                    </Link>
                </Button>
                {active.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2 lg:ml-auto">
                        {active.map(([dimension, key]) => (
                            <button
                                key={dimension}
                                type="button"
                                onClick={() => select(dimension)}
                                className="inline-flex items-center gap-1 rounded-full border bg-primary/10 px-2.5 py-1 text-xs text-foreground hover:bg-primary/20"
                                aria-label={`Remove ${dimension} filter`}
                            >
                                <span className="text-muted-foreground capitalize">
                                    {dimension}:
                                </span>
                                {labels[dimension](key)}
                                <X className="size-3" />
                            </button>
                        ))}
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-7 text-xs"
                            onClick={() => setFilters({})}
                        >
                            Clear all
                        </Button>
                    </div>
                )}
            </div>

            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                <KpiCard label="Leads" value={totals.records} icon={Users} />
                <KpiCard
                    label="Possible leads"
                    value={totals.possible}
                    footnote={`${percent(possibleRate(totals))} of leads`}
                    icon={Sparkles}
                />
                <KpiCard
                    label="Qualified leads"
                    value={totals.qualified}
                    icon={Target}
                />
                <KpiCard
                    label="Forwarded"
                    value={totals.forwarded}
                    icon={Building2}
                />
                <KpiCard label="Countries" value={countryCount} icon={Globe2} />
                <KpiCard
                    label="Cities / capitals"
                    value={cityCount}
                    icon={MapPin}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Visual
                    title="Leads by region"
                    subtitle="Regions follow the Regions.xlsx reference"
                >
                    <ClusteredBars
                        groups={regionGroups}
                        measure={measure}
                        selected={filters.region}
                        onSelect={(key) => select('region', key)}
                    />
                </Visual>
                <Visual
                    title="Leads by country"
                    subtitle="From the country code or saved country name"
                >
                    <ClusteredBars
                        groups={countryGroups}
                        measure={measure}
                        selected={filters.country}
                        onSelect={(key) => select('country', key)}
                    />
                </Visual>
                {showAgents && (
                    <Visual title="Leads by agent" subtitle="Current owner">
                        <ClusteredBars
                            groups={agentGroups}
                            measure={measure}
                            selected={filters.agent}
                            onSelect={(key) => select('agent', key)}
                        />
                    </Visual>
                )}
                <Visual
                    title="Leads by city / capital"
                    subtitle="★ marks the country's capital"
                >
                    <ClusteredBars
                        groups={cityGroups}
                        measure={measure}
                        selected={filters.city}
                        onSelect={(key) => select('city', key)}
                    />
                </Visual>
                <Visual
                    title="Leads in capital cities"
                    subtitle={`${capitalGroups
                        .reduce((sum, row) => sum + row.records, 0)
                        .toLocaleString()} leads in ${capitalGroups.length.toLocaleString()} capitals`}
                    className={showAgents ? 'lg:col-span-2' : ''}
                >
                    <ClusteredBars
                        groups={capitalGroups}
                        measure={measure}
                        selected={filters.city}
                        onSelect={(key) => select('city', key)}
                        empty="No leads in capital cities for the current filters."
                    />
                </Visual>
            </div>

            <Visual
                title="Region, country and city breakdown"
                subtitle="Expand a region or country to drill down · click a column to sort · the list icon opens those leads"
            >
                <DemographicMatrix rows={filtered} linkFor={linkFor} />
            </Visual>
            <p className="text-xs text-muted-foreground">
                Possible, qualified and forwarded use each lead&apos;s current
                status. Countries missing from the regions reference appear as
                Unassigned; cities use the label saved on the lead.
                {demographics.truncated &&
                    ' Only the 5,000 largest region/country/city/agent combinations are shown.'}
            </p>
        </div>
    );
}

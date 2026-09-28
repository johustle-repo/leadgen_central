import { index as leadsIndex } from '@/routes/leads';

/** Report dimensions the lead list can filter by (see LeadReportDimensions). */
export type LeadDrilldown = {
    status?: string;
    source_group?: string;
    data_source?: string;
    country_group?: string;
    company?: string;
    industry?: string;
    agent_id?: string;
};

/** Labels the lead list shows for an active report filter. */
export const DRILLDOWN_LABELS: Record<string, string> = {
    status: 'Status',
    source_group: 'Data source',
    data_source: 'Data source',
    country_group: 'Country',
    company: 'Company',
    industry: 'Industry',
    agent_id: 'Owner',
};

/**
 * Link to the leads behind one report bar, limited to the report's period.
 * Returns undefined for rows that are not a single category ("Other groups").
 */
export function leadDrilldownUrl(
    period: { date_from: string; date_to: string },
    label: string,
    criteria: LeadDrilldown,
): string | undefined {
    if (label === 'Other groups') {
        return undefined;
    }

    return leadsIndex.url({
        query: {
            created_from: period.date_from,
            created_to: period.date_to,
            per_page: '50',
            ...criteria,
        },
    });
}

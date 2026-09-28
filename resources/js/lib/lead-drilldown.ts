import { index as leadsIndex } from '@/routes/leads';
import { index as uploadsIndex } from '@/routes/uploads';

/** Report dimensions the lead list can filter by (see LeadReportDimensions). */
export type LeadDrilldown = {
    status?: string;
    source_group?: string;
    data_source?: string;
    country_group?: string;
    company?: string;
    industry?: string;
    agent_id?: string;
    region?: string;
    city?: string;
    /** Owner id, or 'none' for unassigned leads. */
    agent?: string;
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
    region: 'Region',
    city: 'City',
    agent: 'Owner',
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

/** Import-row outcomes the Upload History list can filter batches by. */
export type RowOutcome =
    | 'accepted'
    | 'needs_review'
    | 'duplicates'
    | 'rejected'
    | 'errors'
    | 'location_issues';

/**
 * Link to the upload batches behind a data quality figure: batches uploaded in
 * the report period, optionally only those with rows of one outcome or from
 * one source (as grouped in the source quality table).
 */
export function uploadDrilldownUrl(
    period: { date_from: string; date_to: string },
    rowOutcome?: RowOutcome,
    rowSource?: string,
): string {
    return uploadsIndex.url({
        query: {
            created_from: period.date_from,
            created_to: period.date_to,
            row_outcome: rowOutcome,
            row_source: rowSource,
        },
    });
}

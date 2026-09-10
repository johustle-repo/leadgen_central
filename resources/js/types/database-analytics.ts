export type Distribution = {
    label: string;
    value: number;
    percent: number | null;
};
export type Overview = Record<
    | 'records'
    | 'companies'
    | 'emails'
    | 'countries'
    | 'cities'
    | 'provinces'
    | 'sources'
    | 'uploads',
    number
>;
export type DatabaseAnalytics = {
    overview: Overview;
    previous: Overview;
    changes: Record<keyof Overview, number | null>;
    all_time: Overview;
    previous_period: { from: string; to: string };
    timezone: string;
    distributions: Record<
        | 'countries'
        | 'industries'
        | 'sources'
        | 'statuses'
        | 'owners'
        | 'entry_methods'
        | 'provinces'
        | 'cities'
        | 'timezones',
        Distribution[]
    >;
    quality: Record<
        | 'rows'
        | 'processed'
        | 'pending'
        | 'accepted'
        | 'needs_review'
        | 'duplicates'
        | 'rejected'
        | 'errors'
        | 'issues',
        number
    > &
        Record<
            | 'accepted_rate'
            | 'duplicates_rate'
            | 'rejected_rate'
            | 'errors_rate'
            | 'average_acceptance_rate'
            | 'average_duplicate_rate'
            | 'average_rows_per_upload',
            number | null
        >;
    missing: Distribution[];
    growth: {
        granularity: 'day' | 'week' | 'month';
        points: Array<{
            date: string;
            records: number;
            companies: number;
            emails: number;
        }>;
        totals: Record<'records' | 'companies' | 'emails', number> &
            Record<
                'records_change' | 'companies_change' | 'emails_change',
                number | null
            >;
    };
    geography: {
        rows: Array<{
            country: string;
            province: string;
            city: string;
            timezone: string;
            records: number;
        }>;
        unverified_city_records: number;
    };
    companies: Array<{ label: string; contacts: number; emails: number }>;
    can_compare_agents: boolean;
    contribution: Array<{
        id: number;
        name: string;
        records: number;
        uploads: number;
        accepted: number;
        duplicates: number;
        rejected: number;
        errors: number;
        issues: number;
        processed: number;
        possible: number;
        qualified: number;
        forwarded: number;
    }>;
};

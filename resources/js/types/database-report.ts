import type { DatabaseAnalytics } from './database-analytics';

export type QualityMetrics = {
    observed_rows: number;
    processed: number;
    accepted: number;
    needs_review: number;
    duplicates: number;
    rejected: number;
    errors: number;
    location_issues: number;
    issues: number;
    clean: number;
    accepted_rate: number | null;
    duplicates_rate: number | null;
    rejected_rate: number | null;
    errors_rate: number | null;
    location_issues_rate: number | null;
    clean_rate: number | null;
};
export type DatabaseReport = Omit<
    DatabaseAnalytics,
    'contribution' | 'quality'
> & {
    quality: DatabaseAnalytics['quality'] &
        QualityMetrics & {
            submitted_rows: number;
            average_batch_size: number | null;
        };
    company_analysis: {
        single_contact: number;
        multiple_contacts: number;
        average_contacts: number | null;
        unnamed_records: number;
    };
    source_quality: (QualityMetrics & { label: string; records: number })[];
    quality_trend: (QualityMetrics & { date: string })[];
    industry_coverage: number | null;
    show_industries: boolean;
    geographic_detail: {
        filters: {
            geo_country: string;
            geo_province: string;
            geo_city: string;
        };
        records: number;
        rows: DatabaseAnalytics['geography']['rows'];
    };
    contribution: (QualityMetrics & {
        id: number;
        name: string;
        records: number;
        companies: number;
        uploads: number;
        average_batch_size: number | null;
    })[];
};

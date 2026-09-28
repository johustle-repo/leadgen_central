/** Display names for how a lead entered the system (App\LeadSource). */
export const ENTRY_METHOD_LABELS: Record<string, string> = {
    csv: 'CSV upload',
    manual: 'Manual entry',
    scraper: 'Scraper',
};

export const entryMethodLabel = (method: string): string =>
    ENTRY_METHOD_LABELS[method] ?? method;

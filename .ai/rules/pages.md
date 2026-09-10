---
paths:
  - 'app/Services/{DashboardReport,DatabaseIntelligenceReport}.php,resources/js/pages/{dashboard,analytics/index}.tsx'
---

# Pages

## Keep database intelligence metrics distinct and owner scoped
Dashboard and Reports analytics (DashboardReport, and DatabaseIntelligenceReport built on top of it for the Reports page) always scope the base lead and upload queries with canViewAllLeads; never reuse the globally searchable lead-list query for Agent metrics. Report period-created surviving records separately from all-time totals and first-seen normalized company/email identities. CSV acceptance includes needs_review and can overlap possible duplicates; calculate rates from non-pending upload_rows, not sums treated as disjoint outcomes. Use current statuses as classifications, not a mandatory funnel, and label geographic/identity limitations.

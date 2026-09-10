# Changelog

All notable changes to LeadGen Central are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project uses
[Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-09-10

Initial tagged release. The app predates this changelog, so this entry covers the
current release's notable changes rather than the project's full history.

### Added

- Database intelligence Reports page (moved to `/report`, previously `/analytics`):
  database summary vs. all-time totals, growth trends, a status classification
  distribution, company/contact analysis, source quality bucketing, upload
  quality, a filterable geographic breakdown, and per-agent contribution, all
  scoped correctly by role.
- Dashboard headline summary and a shared collapsible section component so denser
  panels stay out of the way until opened.
- Change history on the lead edit page: field edits are now recorded (who, when,
  old value, new value) and shown on the lead's edit form.
- A drag-and-drop single-file uploader for CSV leads, with a clearer confirmation
  step before clearing an agent's records.
- Sidebar reorganized into labeled groups (Overview, Leads, Administration,
  Attendance) instead of one flat list.
- App version number, shown in the sidebar footer, and this changelog.

### Changed

- Flat design applied app-wide: removed drop shadows from persistent UI (cards,
  buttons, inputs) and standardized floating overlays (dialogs, dropdowns,
  popovers) to one consistent shadow; replaced decorative gradients with solid
  colors.
- Dashboard trimmed from 10 sections to 5, focused on a fast "what's the state of
  the database" check; the removed detail (upload performance, geography,
  per-agent breakdowns) is still available in full on the Reports page.
- Database growth and data quality trend charts switched from line charts to
  grouped bar charts - the originals rendered sparse, bursty upload activity as a
  misleadingly smooth trend.
- CSV rows with a malformed source link, or that are entirely blank, are no
  longer rejected outright.
- Re-analyze now correctly requeues failed uploads, not just completed ones with
  rejected rows.

### Fixed

- Uniform stat-card heights across Dashboard and Reports.
- Duplicate rows not counted in the Errors total on Upload History.
- Missing database indexes that were causing slow Dashboard, Leads list, and
  Reports geography queries at the current data volume.

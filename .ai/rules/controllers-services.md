---
paths:
  - 'app/Http/Controllers/**/*Controller.php,app/Services/**/*.php'
---

# Controllers Services

## Protect lead CSV exports
Every CSV export containing lead or upload-row data must pass user-controlled cells through CsvCellSanitizer to prevent spreadsheet formula injection. Record each data export in AuditLog and keep export routes behind authorization plus the named data-exports rate limiter.

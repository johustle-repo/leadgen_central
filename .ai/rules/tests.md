---
paths:
  - 'tests/**'
---

# Tests

## Tests must use in-memory SQLite
The base Tests\TestCase must abort unless APP_ENV is testing and the default database is SQLite :memory:. Clear optimized Laravel caches before running tests; never allow RefreshDatabase to target the local MySQL application database.

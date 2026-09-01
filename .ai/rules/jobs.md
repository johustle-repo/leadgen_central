---
paths:
  - app/Jobs/ProcessUploadBatch.php|composer.json
---

# Jobs

## CSV imports run without a worker timeout
Lead CSV imports may include up to the configured multi-file limit and must be allowed to finish. Keep ProcessUploadBatch and the development queue worker at timeout 0; do not reintroduce a fixed queue timeout for CSV processing.

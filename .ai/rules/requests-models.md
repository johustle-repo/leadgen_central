---
paths:
  - 'app/{Http/Controllers,Http/Requests,Models}/**/*LeadAttachment*.php'
---

# Requests Models

## Keep possible-lead documents private
Supporting lead documents are stored on the private local disk and are never exposed by public URLs. Only administrators and sub-administrators may manage them, nested lead/attachment IDs must match, and new files may only be added while the contact is a Possible Lead.

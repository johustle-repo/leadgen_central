---
paths:
  - 'app/{Services,Console/Commands,Models,Http/Controllers}/**/*EmailSequence*.php,app/Services/GmailReplySynchronizer.php'
---

# Services

## Stop outreach sequences when a lead replies
Email sequences send at Day 1, Day 3, and Day 7 through the enrolled lead owner's active Gmail connection. A matched inbound Gmail reply must immediately mark active enrollments as replied and clear next_send_at; the processor must also recheck for replies before every send. Disabled sequences preserve enrollments but do not send.

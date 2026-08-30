---
paths:
  - 'app/{Services,Jobs,Http/Controllers}/**/*Gmail*.php,app/Models/EmailReply.php'
---

# Models

## Store only lead-matched Gmail replies
Gmail synchronization must persist only inbound messages whose normalized sender email matches a lead owned by the connected mailbox's agent. Never copy unrelated inbox mail into LeadGen Central. OAuth tokens remain encrypted and hidden; administrators may view matched reply records but not mailbox tokens.

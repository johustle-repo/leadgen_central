# LeadGen Central: System Processes and Features

Reviewed: September 10, 2026

This document describes the current implementation for Administrators, Sub-administrators, and Agents. Features exclusive to roles outside this scope are omitted. It is based on application routes, policies, requests, services, and database migrations. The configured MySQL connection was unavailable during review, so this document does not certify live deployment behavior or report actual record counts.

## 1. System purpose

LeadGen Central manages contact collection, CSV processing, location cleaning, duplicate handling, lead verification, forwarding, Gmail outreach, and performance reporting. Each lead has an owner, while authorized reviewers can work across the lead database.

## 2. Roles and feature access

| Feature | Administrator | Sub-admin | Agent |
| --- | --- | --- | --- |
| Dashboard and analytics | All-owner scope; administrative comparisons | All-owner scope; fewer analytical panels | Own-data scope |
| Browse leads without a search term | All leads | All leads | Own leads |
| Search the lead list | All leads | All leads | Search can return other owners' lead rows |
| Open and edit lead details | All leads | All leads | Own leads |
| Create leads and upload CSVs | Yes | Yes | Yes |
| View upload batches and results | All batches | All batches | Own batches |
| Review duplicates and verify leads | Yes | Yes | No |
| Manage supporting lead documents | Yes | Yes | No |
| Forward qualified leads | Yes | Yes | No |
| Delete leads | Yes | No | No |
| Delete completed or failed uploads | Yes | No | No |
| Send email and manage personal outreach | Own leads and mailbox | Own leads and mailbox | Own leads and mailbox |
| User management | Subject to account hierarchy | No | No |
| System settings and audit logs | Yes | No | No |
| Personal QR attendance | No | No | Yes |

Sub-admin access to leads is not restricted to their team. Global lead-list search is an exception to the Agent's normal ownership scope; it does not grant permission to open or edit another owner's lead. Sending outreach also remains ownership-restricted even when the user can review all leads.

## 3. Access and navigation

1. The user signs in.
2. Main operational routes require an authenticated session, verified email, and an active account.
3. The dashboard displays data and controls appropriate to the role.
4. The user opens Leads, Uploads, Analytics, or another permitted workspace.
5. The server checks permission again when the user views or changes a protected record.

Profile, security, appearance, and personal QR attendance have their own route requirements. Personal QR attendance is Agent-only.

## 4. Manual lead entry

**Actors:** All three roles.

1. Open the lead creation form.
2. Enter the company, contact person, and email address. These are required for ordinary manual creation.
3. Add available website, phone, location, industry, position, LinkedIn, source, and notes information.
4. Submit the form.
5. The system validates input, normalizes contact and company data, and attempts location matching.
6. If an existing active lead uses the normalized email address, creation is rejected with information identifying the existing lead.
7. Otherwise, the system saves the lead, assigns ownership and a lead code, records its creator, and starts it with the `raw` status.

**Result:** An owned contact record ready for further work. The contact-per-company limit is currently disabled in the creation service; the declared value of 10 is not an enforced limit.

## 5. CSV upload and processing

**Actors:** All three roles, with batch visibility determined by permissions.

1. Select one or more CSV files. File count and size limits come from system settings or configuration.
2. Choose duplicate handling: flag duplicates or update missing values where eligible.
3. Map file headers to the application's lead fields.
4. Submit the batch for background processing.
5. The processor reads rows, checks structure, normalizes values, validates fields, and matches locations.
6. For matched contacts, it records a duplicate or applies an eligible update/cleaning operation. Updating missing values is restricted to a matching lead owned by the uploading user.
7. For new contacts, it creates lead records and links them to the upload rows.
8. It records each row's result and calculates the batch summary.
9. The user reviews results and downloads available cleaned or error CSV files.

| Row result | Meaning |
| --- | --- |
| Accepted | Row was accepted for creation or an eligible update |
| Needs review | Row requires attention, such as unresolved location matching |
| Duplicate | Row matches an existing contact; original ownership is retained |
| Rejected | Structural or validation requirements were not met |
| Error | A processing operation failed |

A completed batch can contain rejected, duplicate, or error rows. Batch completion means processing finished, not that every row succeeded. Batch summary categories can overlap: for example, accepted totals include rows needing review.

New CSV leads normally start as `validated`; location review is also represented through validation metadata and row results. Do not interpret every status field as the same business measure.

Users with access to a batch can retry pending batches and reanalyze completed or failed batches. Mapping updates are limited to the owner while the batch is pending. Administrators can delete completed or failed uploads.

## 6. Duplicate detection and review

**Review actors:** Administrator and Sub-admin.

The current detector matches by normalized email when present. Without an email, it checks a matching contact name against leads that also have no email. It does not currently implement fuzzy company-name scoring, despite retaining possible-match fields and review functionality.

Exact upload matches are logged as confirmed duplicates. The duplicate review workspace lists pending matches when such records exist.

Review process:

1. Open a pending match and compare the incoming and existing information.
2. Choose **Confirm duplicate**, **Not duplicate**, or **Keep both**.
3. The system saves the decision, reviewer, and review time.
4. Confirming a match with an incoming lead changes that lead to `duplicate` and records the status change.

Clearing a match or keeping both records does not automatically merge contacts or qualify the incoming lead.

## 7. Lead verification and qualification

**Actors:** Administrator and Sub-admin.

1. Open the verification workspace and search or filter the review queue.
2. Inspect contact information, location, owner, upload origin, notes, documents, and available history.
3. Correct details and select a verification outcome.
4. Add remarks and save, optionally proceeding to the next record.
5. The system normalizes updated values, saves verification time and reviewer, and records a history entry when the status changes.

| Verification outcome | Purpose |
| --- | --- |
| Possible Lead | Candidate for additional assessment or supporting documents |
| Qualified Lead | Contact marked qualified and eligible for forwarding |
| Not a Lead | Contact marked unsuitable |
| Needs Review | Further assessment required |
| Duplicate | Contact identified as a duplicate |

These are selectable outcomes, not a mandatory sequence. A lead need not pass through Possible Lead before becoming Qualified Lead. The system also provides a dedicated process to create a Possible Lead.

## 8. Notes and supporting documents

Lead records support free-text notes and separate structured note records with author attribution. Notes provide context alongside verification and forwarding history.

Administrators and Sub-admins can add supporting documents only while a lead is a Possible Lead. Supported types are PDF, CSV, XLS, XLSX, DOC, and DOCX, with a 20 MB upload limit per document. Documents have an optional label and retain uploader and file metadata.

Files are stored privately. Authorized download and deletion operations check that the attachment belongs to the requested lead. The database stores file metadata and a storage path, rather than the document contents.

## 9. Lead forwarding

**Actors:** Administrator and Sub-admin.

1. Open a Qualified Lead.
2. Supply the applicable recipient user, recipient name/email, team, and remarks.
3. Submit the forwarding action.
4. The system saves the forwarding record and timestamp.
5. The lead changes from `qualified_lead` to `forwarded`, and the status change is recorded.

Forwarding records a handoff. This service does not send an email or change the lead's owner. Leads in other statuses are rejected by this action.

## 10. Gmail outreach

**Actors:** Users managing their own mailbox and owned leads.

### Individual email

1. Connect an active Gmail mailbox.
2. Open an owned lead with a valid email address.
3. Supply a subject and message body.
4. Send the message through Gmail with the configured brochure.
5. The system displays success or an error and records a successful send in the audit log.

Mailbox authorization and brochure configuration must be valid. Permission to edit another owner's lead does not permit sending through this action for that lead.

### Automated sequence

1. Configure the personal sequence's name, message steps, brochure choices, and enabled state.
2. Enroll an owned lead. The interface offers leads with email addresses and excludes those with active or pending enrollments.
3. An active Gmail connection and enabled sequence are required for enrollment.
4. The scheduler processes due enrollments. The default sequence sends on Day 1, Day 3, and Day 7 relative to enrollment.
5. Before sending, the processor checks for a matched reply received since enrollment began.
6. A matched reply stops the active enrollment, marks it `replied`, and clears its next send time.
7. Otherwise, a successful send creates a message record and schedules the next step, or completes the enrollment after the final step.
8. The owner can cancel an enrollment. Disabling the sequence preserves enrollments while preventing scheduled sends.

Missing active Gmail connections and send-processing failures can mark an enrollment failed. Personalization supports `{{firstName}}` and `{{companyName}}`.

Background synchronization stores only inbound messages matched to leads owned by the connected mailbox's user. The email reply inbox is not available to the three roles documented here; automatic reply-based sequence stopping still operates.

## 11. Dashboard, analytics, and exports

**All roles:** Role-scoped summaries, date-based analytics, daily activity, lead status distributions, source and country breakdowns, and available CSV/PDF analytics exports.

**Administrator:** Additional agent performance comparisons, funnel information, data-quality trends, upload timing analysis, and industry breakdowns.

**Sub-admin:** All-owner lead and upload scope, with fewer analytical panels than the Administrator.

**Agent:** Own-data reporting. Global lead-list search does not expand dashboard or analytics ownership scope.

Lead exports include raw and cleaned CSV outputs. Uploads offer error and cleaned-row exports. Reviewers can export Possible Leads. Export endpoints apply authorization and rate limits; lead and upload CSV values are sanitized to reduce spreadsheet formula injection risk, and data exports are audited.

Reply-specific analytics are restricted for these roles; hidden or zeroed reply fields should not be interpreted as measured absence of replies.

## 12. Administration

Administrators can manage users within the account hierarchy, view audit logs, and manage system settings. They can assign Agent and Sub-admin roles. They cannot edit another Administrator account through ordinary user management and cannot delete their own account.

System-setting updates require permission and password confirmation. Audit logs record selected application events, actor information, and contextual metadata; they are not a complete database change log. The audit policy does not allow users to create, edit, or delete audit entries directly.

Deleting a lead through ordinary lead deletion is a soft delete. Deleting an upload removes its batch, row history, and stored file while preserving associated lead records with their batch reference cleared. Deleting an upload is therefore not equivalent to deleting its leads.

## 13. Agent QR attendance

1. The Agent opens personal QR attendance settings.
2. The Agent submits their own badge value for time-in or time-out.
3. The system verifies ownership and active account status.
4. It checks for a duplicate scan within two minutes and validates the day's attendance sequence.
5. It records the event and an audit entry.
6. The Agent can see their recent check-ins.

The scan service requires time-in before time-out and blocks another cycle after that day's completed time-out. Attendance management, imports, and organization-wide attendance reports are outside the scope of this document.

## 14. Background processing and data storage

The scheduler is configured to run Gmail synchronization, sequence processing, pending-upload dispatch, and database queue draining every minute. Actual execution requires a functioning scheduler, database, queue configuration, and relevant external services. A scheduled minute is not a guaranteed delivery time.

| Data area | Main tables |
| --- | --- |
| Accounts and ownership | `users` |
| Contacts and activity | `leads`, `lead_notes`, `lead_status_histories`, `lead_forwardings`, `lead_attachments` |
| CSV processing | `upload_batches`, `upload_rows` |
| Duplicate tracking | `duplicate_matches`, `duplicate_logs` |
| Location cleaning | `countries`, `cities`, `location_aliases`, `timezone_references` |
| Outreach | `gmail_connections`, `email_sequences`, `email_sequence_enrollments`, `email_sequence_messages`, `email_replies` |
| Personal attendance | `attendances` |
| Administration | `system_settings`, `audit_logs` |

Gmail tokens are encrypted and hidden by the model. Original CSV data and processed row data are retained separately for troubleshooting and review.

## 15. Source references and verification boundary

This documentation was checked against:

- [Application routes](routes/web.php) and [settings routes](routes/settings.php).
- [Role helpers](app/Models/User.php), [lead policy](app/Policies/LeadPolicy.php), [upload policy](app/Policies/UploadBatchPolicy.php), and [user policy](app/Policies/UserPolicy.php).
- [Lead listing and search](app/Http/Controllers/LeadController.php).
- [Lead creation](app/Services/LeadCreator.php), [CSV processing](app/Services/UploadBatchProcessor.php), and [duplicate detection](app/Services/DuplicateDetectionService.php).
- [Verification](app/Services/LeadVerificationService.php), [verification request](app/Http/Requests/VerifyLeadRequest.php), and [forwarding](app/Services/LeadForwardingService.php).
- [Document upload requirements](app/Http/Requests/StoreLeadAttachmentRequest.php).
- [Individual email](app/Http/Controllers/SendLeadEmailController.php), [sequence management](app/Http/Controllers/EmailSequenceController.php), and [sequence processing](app/Services/EmailSequenceProcessor.php).
- [Analytics](app/Services/AnalyticsReport.php), [attendance scanning](app/Services/AttendanceScanService.php), and [scheduled tasks](routes/console.php).

No application code or database records were changed to produce this document. Processes were reviewed statically, not exercised against a running database. The earlier database review identified unresolved risks involving soft-deleted leads in sequence processing, concurrent attendance requests, and duplicate external email sends; this feature inventory does not certify those paths as failure-proof.

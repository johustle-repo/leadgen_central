# LeadGen Central — Demo Walkthrough Script

A presenter's script for demoing LeadGen Central to stakeholders. Covers the **Agent**, **Sub-Administrator**, and **Administrator** roles only — Super Administrator (system owner-level access: attendance, impersonation, Gmail-on-behalf-of, the Email Replies inbox) is intentionally out of scope for this demo.

## Before you start

- Have three test accounts ready, one per role: an **Agent**, a **Sub-Administrator**, and an **Administrator**. Log in as each in a separate browser profile (or use incognito windows) so you can switch quickly without logging in/out on stage.
- Prepare one sample CSV to upload live. For the best demo, seed it with a few intentional problems:
  - A row that duplicates an existing lead already in the system (shows the duplicate-detection flow).
  - A row missing a required field, like Company Name (shows row-level rejection).
  - A handful of normal, clean rows (shows the happy path).
- Have at least one lead already sitting in "Needs Review" / possible-duplicate status before the demo starts, so Lead Review and Duplicate Review aren't empty screens.
- Know the story arc: **Agent brings data in → Sub-Administrator reviews and cleans it → Administrator manages the people and the system.** Frame the whole demo around that arc rather than a flat feature list.

## The pitch (30 seconds, say this before logging in)

"LeadGen Central is where our team turns raw, messy lead lists into a clean, de-duplicated, actionable pipeline. Agents upload and work their own leads. Sub-administrators review the whole team's pipeline for quality — catching duplicates and validating unclear leads. Administrators run the system itself — who has access, what the rules are, and a full audit trail of everything that happened."

---

## Part 1 — The Agent

Agents own their own leads and their own uploads. They cannot see other agents' data, cannot delete anything, and don't do review/QA work — that's the next role up.

### 1.1 Dashboard

Log in as the Agent. Land on **Dashboard**.

> "This is scoped entirely to *this* agent — their leads, their upload activity, their status breakdown. An agent never sees anyone else's numbers here."

Point out: total leads, status breakdown (qualified / possible / needs review / forwarded), and recent activity.

### 1.2 Lead Reports

Click **Lead Reports** in the sidebar.

> "Same idea — this is their personal performance report, not a team-wide one. They can filter by date range and see their own trend over time."

### 1.3 Uploading leads

Click **Upload Leads**.

1. Upload the prepared sample CSV.
2. If prompted, walk through the **column mapping** screen — this is where a CSV's own headers (however messy) get mapped to LeadGen Central's fields (Company Name, Email, Website, etc.).
3. Point out the **duplicate handling** choice at upload time (e.g. treat exact repeats as updates to existing records vs. flag them for review).
4. Submit, then go to **Upload History**.

> "Processing is asynchronous — the file goes through a background job that cleans, validates, and de-duplicates every row automatically."

On the Upload History table, show:
- **Status** progression (Pending → Processing → Completed/Failed).
- Row counts: total, accepted, rejected, duplicate.
- Click into a batch to show the **per-row detail** — which rows were accepted, which were rejected and why (e.g. missing Company Name), which were flagged as duplicates.
- Point out the **Re-analyze** action (re-runs a completed/failed batch's problem rows against the latest rules) and **Retry processing** for anything still stuck pending.
- Show downloading the **raw** and **cleaned** CSV exports, and the **problem rows** export for anything rejected.

### 1.4 Working a lead

Go to **Leads**. This list is scoped to leads this agent owns.

Open one lead and show:
- **Notes** — free-text history on the lead.
- **Attachments** — upload a supporting file.
- **Forward** — hand the lead off (e.g. to another team/agent).
- **Send Email** — send directly to the lead's contact from their connected Gmail.
- **Enroll in an email sequence** — automated multi-day outreach; each agent configures their own sequence template and can enroll/cancel individual leads.

> "Everything here is self-service. An agent manages their own pipeline end-to-end without needing an admin to touch anything."

### 1.5 Account settings

Click the user menu → **Settings**. Show:
- **Profile** (name/email).
- **Appearance** (light/dark theme).
- **Security** (password, two-factor authentication).
- **Connect Gmail** — this is what powers Send Email and the email sequence follow-ups; each user connects their own account.

### What an Agent can't do (say this out loud)

- Can't see another agent's leads or uploads.
- Can't delete a lead or an upload batch.
- No **Lead Review** or **Duplicate Review** — those are quality-control steps for the next role.
- No access to Users, Audit Logs, or System Settings.

---

## Part 2 — The Sub-Administrator

Sub-administrators are the quality-control layer. They see everything every agent brings in, but they don't manage people or system configuration — that's the Administrator's job.

Switch to the Sub-Administrator account.

### 2.1 Dashboard and Lead Reports

> "Same screens as the agent saw — but now it's team-wide."

Show the **Dashboard**: it now aggregates every agent's leads and uploads, and includes a **per-agent breakdown** (a small leaderboard of each agent's volume and lead quality). Same story on **Lead Reports**, with an agent filter available.

### 2.2 Leads — team-wide visibility

Open **Leads**. Point out the agent filter and the fact that this list now includes *every* agent's leads.

> "A sub-administrator can open, edit, and correct any agent's lead — but notice there's no delete option here. Deleting is reserved for administrators."

### 2.3 Upload History — team-wide

Same as above: **Upload History** now shows every agent's uploads, filterable by agent. A sub-administrator can re-analyze or retry any batch, but — like leads — can't delete one.

### 2.4 Lead Review

Click **Lead Review**. This is new for this role.

> "This is the queue of leads the system couldn't confidently classify on its own — usually because of an unclear or unmatched location, or ambiguous data. A sub-administrator reviews each one and decides: qualified lead, possible lead, or not a lead at all."

Open one, walk through the decision, and show the **possible-leads** export (a working list of ones still under review) and the option to manually add one to that list.

### 2.5 Duplicate Review

Click **Duplicate Review**.

> "When the system finds a *possible* — not exact — match against an existing lead, it doesn't silently drop it or silently keep it. It puts it here for a human to decide: is this really the same company/contact, or a coincidence?"

Open one match, show the side-by-side comparison, and resolve it (confirm as duplicate, or dismiss as unrelated).

### What a Sub-Administrator can't do (say this out loud)

- Can't delete a lead or an upload batch (view/edit only).
- No access to **Users**, **Audit Logs**, or **System Settings** — that's Administrator and above only.
- No attendance, impersonation, or Gmail-on-behalf-of features.

---

## Part 3 — The Administrator

Administrators get everything a Sub-Administrator has, plus the ability to manage people, delete records, and configure the system.

Switch to the Administrator account.

### 3.1 Everything from Part 2, plus delete

Revisit **Leads** and **Upload History** briefly to show the **Delete** action is now available (leads can be individually or bulk-deleted; upload history entries can be deleted once completed/failed — note that deleting an upload's *history* does not remove the leads it already created).

### 3.2 Users

Click **Users**.

> "This is where accounts are managed — creating agents and sub-administrators, deactivating people who've left, and controlling who has what role."

Show:
- Creating a new user and assigning a role.
- Editing an existing user.
- The **email sequence** toggle per user (turning automated outreach on/off for a specific agent).
- Point out the tiering rule: *"An administrator can manage agents and sub-administrators, but not another administrator — only a Super Administrator can touch admin-level accounts. That's a deliberate safeguard."*

### 3.3 Audit Logs

Click **Audit Logs**.

> "Every meaningful action in the system — logins, deletions, uploads, user changes — is recorded here with who did it, when, and from what IP. This is the accountability trail for the whole platform."

Filter by action type or user to show how granular it is.

### 3.4 System Settings

Click **System Settings**.

> "This is where operational limits live — for example, the maximum CSV file size or file count per upload. Administrator-level configuration, not something agents or reviewers need to touch."

### What's still off-limits at this level (say this out loud)

- Attendance / QR check-in features, impersonating another user, clearing a user's records, connecting Gmail *on behalf of* another user, and the Email Replies inbox are all reserved for Super Administrator — the platform-owner tier, intentionally excluded from this demo.

---

## Closing talking points

- **One pipeline, three levels of responsibility**: agents bring data in and work it; sub-administrators guard its quality; administrators run the system and its people.
- **Nothing silently disappears**: rejected rows, duplicates, and possible leads are always surfaced for a human decision, never dropped quietly.
- **Every destructive or sensitive action is logged** (Audit Logs) and permission-gated by role — nobody can quietly delete or reassign something above their tier.
- Invite questions, and offer to log in as any of the three roles again to show a specific workflow in more depth.

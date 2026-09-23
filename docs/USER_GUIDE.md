# TRAC JHS SARMS — User Guide

**LAN-Based Student Admission and Records Management System**
Tawi-Tawi Regional Agricultural College Junior High School (TRAC JHS)
Bongao, Tawi-Tawi · BARMM

This guide describes the system **as it actually behaves at revision `a5e19a0`**,
based on repository inspection and a live runtime/browser audit (PHP +
PostgreSQL + Playwright/Chromium, seeded data, registrar and encoder
accounts, mobile emulation, keyboard and error-path testing).

Every feature in this guide carries one of five status labels:

| Label | Meaning |
|---|---|
| **VERIFIED WORKING** | Confirmed working in a live browser during the audit |
| **IMPLEMENTED / CODE-VERIFIED** | Exists in the source code; full runtime execution not demonstrated |
| **KNOWN BROKEN** | Runtime evidence shows the workflow currently fails |
| **DEGRADED** | Works partially; an important part is unreliable or unresolved |
| **NOT VERIFIED** | Insufficient evidence to claim it works |

Where a workflow is broken, this guide says so plainly, describes what the
user actually sees, and states that a software fix is required. Do not
repeat a failed operation expecting a different result — the failures
documented here are deterministic.

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [User Roles](#2-user-roles)
3. [Getting Started — Installation](#3-getting-started--installation)
4. [LAN Deployment](#4-lan-deployment)
5. [Production Deployment (Render)](#5-production-deployment-render)
6. [Signing In and Out](#6-signing-in-and-out)
7. [Dashboard](#7-dashboard)
8. [Admissions](#8-admissions)
9. [Student Records](#9-student-records)
10. [Academic Records](#10-academic-records)
11. [SF10 Permanent Record](#11-sf10-permanent-record)
12. [Enrollment and Section Assignment](#12-enrollment-and-section-assignment)
13. [Transfers](#13-transfers)
14. [Search](#14-search)
15. [Reports](#15-reports)
16. [LIS CSV Export / Import](#16-lis-csv-export--import)
17. [School Year and Section Management](#17-school-year-and-section-management)
18. [User Management](#18-user-management)
19. [Audit Log](#19-audit-log)
20. [Notifications](#20-notifications)
21. [Backup and Restore](#21-backup-and-restore)
22. [Password Change](#22-password-change)
23. [Public Pages](#23-public-pages)
24. [Mobile Usage](#24-mobile-usage)
25. [Accessibility](#25-accessibility)
26. [Security](#26-security)
27. [Database Overview](#27-database-overview)
28. [Verified User Journeys](#28-verified-user-journeys)
29. [Feature Verification Matrix](#29-feature-verification-matrix)
30. [Troubleshooting](#30-troubleshooting)
31. [Current System Status](#31-current-system-status)

---

## 1. System Overview

TRAC JHS SARMS is a browser-based records system for the school registrar
and data encoders. It runs on the school's local network (LAN). It is
**not** a public-facing admission portal — members of the public see a
landing page and an inquiry form only.

Core modules:

| Module | What it does |
|---|---|
| Admissions | Register and review applicant applications |
| Students | Student profiles and status |
| Enrollments | Year enrollment and section assignment |
| Records | Academic records, SF10 grade entries, student status |
| Transfers | Incoming/outgoing transfer requests with a 30-day SLA |
| Search | Find students by name or ID |
| Reports | Enrollment summary, admission status, master list, SF10 |
| LIS | DepEd LIS CSV export/import (SF1-aligned) |
| Administration | Users, settings, audit, backup, restore |

**Verified core lifecycle** (fully demonstrated in the audit):

> Admission → Approve & Enroll → Student Record → SF10 Grade Entry → SF10 Report

**Currently broken workflows** (see the sections flagged KNOWN BROKEN):

> Public inquiry submission · Section assignment · Transfer creation and
> status updates · Add School Year · Add Section · Restore execution ·
> Report printing

---

## 2. User Roles

The system implements exactly two roles.

### 2.1 Registrar

The School Registrar has full access. Sidebar groups visible:
Dashboard; Admissions (Admissions, Students, Enrollments, Records);
Reports (Reports, Transfers, Search); Administration (Users, Settings,
Audit, LIS, Backup, Restore).

Registrar capabilities, with current status:

| Capability | Status |
|---|---|
| Create / edit / review admissions | VERIFIED WORKING |
| Approve & Enroll (creates student + enrollment) | VERIFIED WORKING |
| Reject admissions | VERIFIED WORKING |
| View / edit student records | VERIFIED WORKING |
| Enter SF10 grades | VERIFIED WORKING |
| Save academic records | VERIFIED WORKING (see 10 for a form-validation caveat) |
| Generate reports (on screen) | VERIFIED WORKING |
| Print reports | KNOWN BROKEN (see 15) |
| Assign sections | KNOWN BROKEN (see 12) |
| Create transfer requests | KNOWN BROKEN (see 13) |
| Update transfer status (send/receive/complete/escalate/reopen) | KNOWN BROKEN (see 13) |
| Add school year / add section | KNOWN BROKEN (see 17) |
| Switch active school year | VERIFIED WORKING |
| Create / enable / disable users; clear login throttle | IMPLEMENTED / CODE-VERIFIED |
| Export database backup | VERIFIED WORKING |
| Restore database | KNOWN BROKEN — data-destroying; see 21 |
| LIS export / template / import | VERIFIED WORKING |
| View audit log | VERIFIED WORKING |
| Change password | VERIFIED WORKING |

### 2.2 Encoder

The Data Encoder is a restricted data-entry role. Sidebar groups visible:
Dashboard; Admissions; Reports (Reports, Transfers, Search).
The Administration group is hidden.

Verified behavior (live test):

- Direct URL access to `/modules/admin/users.php`, `settings.php`,
  `audit.php`, `backup.php`, `lis.php`, `restore.php` is blocked with the
  message **"Only the School Registrar can perform this action."**
- Encoder can reach Admissions → New Admission and the other data-entry
  pages.

Encoder workflows not exercised in the audit (admission creation as
encoder, encoder SF10 entry) are **NOT VERIFIED** individually — the
pages are reachable, but the audit performed those actions as registrar.

---

## 3. Getting Started — Installation

### 3.1 Requirements

- PHP **8.1+** with the `pdo_pgsql` extension
- PostgreSQL client tools on `PATH`: `initdb`, `pg_ctl`, `psql`, `pg_isready`
- Git
- **No Composer. No npm. No build step.**

### 3.2 Quick start

```bash
git clone https://github.com/kerrfairtex/LAN-Based-Student-Admission-and-Records-Management-System.git
cd LAN-Based-Student-Admission-and-Records-Management-System
bash tools/dev-up.sh
```

`dev-up.sh` (which calls `tools/dev-up.php`, the cross-platform source of
truth) does everything:

1. Verifies PHP + PostgreSQL tools.
2. Creates an embedded PostgreSQL cluster in `.pgdata/` (gitignored).
3. Starts it on **port 5433**.
4. Imports `database/schema.sql` — 14 tables, 2 seed accounts.
5. Starts PHP's built-in dev server on **port 8000**, bound to all interfaces.
6. Prints the sign-in URLs.

Boot banner:

```
TRAC JHS SARMS is live.
  Loopback : http://127.0.0.1:8000/
  LAN      : http://192.168.x.x:8000/
```

### 3.3 Seed accounts — change immediately

Two accounts are committed to `database/schema.sql` so a fresh clone is
usable. **These are development/bootstrap credentials. Because the
repository is public, anyone can clone it and read the bcrypt hashes.
Change both passwords on first sign-in** under
**Account → Change Password** (sidebar footer).

The exact seed usernames and passwords are documented in the repository
README. Do not leave them unchanged on any shared installation.

### 3.4 Stopping and re-running

- Stop: press **Ctrl+C** in the terminal running `dev-up.sh`. The shutdown
  handler stops the embedded PostgreSQL cleanly.
- Re-run: `dev-up.sh` is idempotent. If `.pgdata/` exists with a matching
  PostgreSQL major version it reuses the cluster and re-imports the
  schema (all `CREATE` statements use `IF NOT EXISTS`).

### 3.5 Known fresh-install gap

**DEGRADED — inquiry table.** `dev-up.sh` imports `schema.sql` only. The
`inquiries` table used by the public landing form is created by
`database/migrations/005_inquiries.sql`, which is **not** part of the
dev-up import. On a fresh install the public inquiry form cannot save
rows until that migration is applied manually. (The inquiry form has a
further, separate defect — see 23.)

Migrations `002_phase2.sql` and `003_lis_csv.sql` are MySQL-flavored
upgrades for old installs; **do not run them on a fresh Postgres
database**. `004_audit_retention.sql` and `006_audit_user_nullable.sql`
are Postgres-compatible.

---

## 4. LAN Deployment

The system is designed for a **trusted LAN**:

- Run `dev-up.sh` on the server machine (any Linux/macOS/WSL box, or
  Termux on Android).
- The PHP dev server binds `0.0.0.0:8000`, so every device on the same
  network can reach it at `http://<server-LAN-IP>:8000/`.
- Client devices need only a browser — no installation.
- Firewall: allow inbound TCP **8000** on the server. (Embedded Postgres
  on 5433 is local-only; do not expose it.)

**TLS limitation.** There is no built-in TLS. Credentials and session
cookies travel unencrypted on the LAN. This is an accepted design
assumption for a trusted school network. For access over the public
internet (registrar at home, branch encoder), put the system behind a
reverse proxy (nginx, Caddy) with Let's Encrypt — **do not expose the
PHP built-in server directly to the public internet**; it is a
development server.

---

## 5. Production Deployment (Render)

The repository ships a production path used by the live deployment:

- **Dockerfile** — `php:8.3-apache` + `pdo_pgsql`, `AllowOverride All`
  (so `.htaccess` deny rules apply), Apache server tokens suppressed,
  opcache disabled.
- **docker-entrypoint.sh** — sets `search_path`, binds Apache to
  `$PORT`, pre-creates persistent-disk subdirectories (`.sessions`,
  `backups`, `uploads`) with correct ownership, resolves the base image's
  dual-MPM conflict, boots Apache.
- **render.yaml** — one Docker web service named `trac-jhs-sarms`,
  persistent disk (1 GB) mounted at `/var/www/html/storage`, healthcheck
  at `/healthcheck.php`, `autoDeploy: false` (deploys are explicit).
- **Database** — managed PostgreSQL (port 5432, `sslmode=require`),
  configured via `DB_*` env vars pasted from the provider dashboard.
- **healthcheck.php** — returns HTTP 200 with JSON
  `{"status":"ok","db":"reachable|unreachable",...}`. A 200 with
  `db: unreachable` means PHP is up but Postgres is not.

Live instance: `https://trac-jhs-sarms.onrender.com` (branch `main`).

**Status: IMPLEMENTED / CODE-VERIFIED.** The deployment artifacts exist
and the live service runs, but the audit tested the local environment,
not the Render deployment. AGENTS.md records two known live-only issues
(PHP warnings visible on the backup page, and `download_backup.php`
returning 404 on live due to deployed-commit drift) — treat live backup
download as **NOT VERIFIED**.

---

## 6. Signing In and Out

### 6.1 Sign in — VERIFIED WORKING

- **Who:** registrar and encoder.
- **Navigation:** public landing → **Staff Sign In** (top-right), or go
  directly to `/auth/login.php`.

Steps:

1. Open the landing page.
2. Click **Staff Sign In**.
3. Enter **Username** and **Password** (the login card labels them;
   the username field is auto-focused).
4. Click **Sign In**.

Expected result: redirect to **Dashboard**.

Failure behavior (verified): wrong password shows
**"Invalid credentials or inactive account."** Repeated failures
trigger throttling based on username/IP (see 26).

### 6.2 Sign out — VERIFIED WORKING

- **Navigation:** sidebar footer → **Sign Out**.

Expected result: redirect to the login page with
`?reason=logout`. Session is destroyed; visiting any protected page
afterwards bounces to the landing page.

---

## 7. Dashboard

**VERIFIED WORKING** · Who: all signed-in users

Navigation: sidebar → **Dashboard**.

Five stat cards (with count-up animation):

| Card | Meaning |
|---|---|
| Active Students | students with status `active` |
| Pending Admissions | admissions awaiting review |
| Enrolled (active year) | enrollments for the active school year |
| Unassigned Sections | enrolled students without a section |
| Overdue Transfers | open transfers past their 30-day SLA due date |

Plus six quick-action tiles (registrar sees all six: New Admission,
Transfer, Search, Reports, Backup, LIS CSV; encoder sees four) and a
**Recent Activity** feed from the audit log.

Verified: card values matched database truth exactly after a full
lifecycle run. Note for screen-reader users: values animate from 0 —
wait for the animation or read the card label first (see 25).

---

## 8. Admissions

### 8.1 Admission list — VERIFIED WORKING

Navigation: **Dashboard → Admissions** (`/modules/admission/index.php`).

Filter by query text, status (pending/approved/rejected), school year,
grade level, and enrollment type; paginated 20 per page. Empty state
distinguishes "No admission applications yet" from "No admission
applications match the current filters."

### 8.2 Create admission — VERIFIED WORKING

- **Who:** registrar and encoder (page reachable by both; audit ran it
  as registrar).
- **Navigation:** **Dashboard → Admissions → New Admission** (or
  quick-action tile).

Fields: School Year*, Grade Level*, Enrollment Type (New / Returning /
Transferee), First Name*, Middle Name, Last Name*, Suffix, LRN
(12 digits, validated), Birthdate*, Sex*, Address*, Contact Number,
Guardian Name*, Guardian Relationship*, Guardian Contact*, Previous
School, Remarks, and three document checkboxes (PSA Birth Certificate,
Report Card/SF9, Good Moral Character).

Steps:

1. Select School Year and Grade Level.
2. Fill the applicant and guardian fields.
3. Tick documents already submitted.
4. Click **Submit Application**.

Expected result (verified): flash
**"Application ADM-2026-0001 encoded successfully."** and redirect to
the application's view page.

### 8.3 View admission — VERIFIED WORKING

Navigation: **Admissions → (application) → View / Edit**.

Shows applicant details, documents submitted, review status, and (for
pending applications) the decision buttons **Approve & Enroll** and
**Reject Application**.

### 8.4 Approve & Enroll — VERIFIED WORKING

- **Who:** registrar and encoder (audit verified as registrar).
- **Navigation:** **Admissions → View application → Approve & Enroll**.

What happens (verified end-to-end): the system creates the student
record, creates an enrollment for the active school year, and redirects
to the new student record page with flash
**"Application approved. Student ID: TRAC-2026-0001"**.

Guardrails implemented (code-verified): refuses a duplicate LRN
("A student with this LRN already exists. Resolve the duplicate before
approving."), refuses a second open incoming transfer for the same
student, and validates any chosen section against the application's
grade level.

### 8.5 Reject admission — VERIFIED WORKING

Navigation: **Admissions → View application → Reject Application**.

Expected result (verified): flash **"Application has been rejected."**
The application status changes to rejected; no student is created.

### 8.6 Edit admission — IMPLEMENTED / CODE-VERIFIED

`/modules/admission/edit.php` exists with the same field set as create.
Not exercised in the audit.

---

## 9. Student Records

### 9.1 Student list — VERIFIED WORKING

Navigation: **Dashboard → Students** (`/modules/records/index.php`).
Filters: query, status (active/transferred/graduated/dropped), school
year, grade; paginated. On narrow screens the table scrolls
horizontally inside its wrapper (verified on a 390 px viewport).

### 9.2 View student — VERIFIED WORKING

Navigation: **Students → (student) → View**.

Shows profile, guardian information, enrollment history, and links to
Academic Record, Edit, Print, and SF10 entry. Invalid IDs redirect to
the list with **"Student record not found."** (verified for
nonexistent, non-numeric, negative, and injection-style IDs).

### 9.3 Edit student — IMPLEMENTED / CODE-VERIFIED

`/modules/records/edit.php` — profile fields plus a Status dropdown
(active / transferred / graduated / dropped). Not exercised in the
audit.

### 9.4 Student status page — IMPLEMENTED / CODE-VERIFIED

`/modules/records/status.php` exposes status transitions; some options
are marked "(registrar only)". Not exercised in the audit.

---

## 10. Academic Records

**VERIFIED WORKING — with a form-validation caveat.**

- **Who:** registrar (encoder reachability not separately tested).
- **Navigation:** **Dashboard → Records** (`/modules/records/academic.php`)
  or from a student's view page.

Fields: School Year* (defaults to active year), Grade Level*
(defaults to "Select grade"), General Average (0–100, numeric),
Promotional Status (Select / Promoted / Retained / Incomplete),
Attendance Days (non-negative integer), Awards, Record Notes.

Steps:

1. Open the Academic Record page for the student.
2. **Select a Grade Level** — this is required.
3. Enter the general average, promotional status, attendance.
4. Click **Save Record**.

Expected result (verified): flash **"Academic record saved."** and
redirect to the student's view page. The record is inserted or updated
per school year, and the action is written to the audit log.

**Caveat (verified):** if a required field is missing (e.g. Grade Level
left at "Select grade"), the form re-renders **without any error
summary** — no flash message appears and the URL does not change. The
save silently does not happen. If nothing seems to happen after
clicking **Save Record**, check that School Year and Grade Level are
both selected. This missing error feedback is a known UX defect; the
workaround is field completion, and a proper fix belongs in software.

---

## 11. SF10 Permanent Record

### 11.1 SF10 grade entry — VERIFIED WORKING

- **Navigation:** from a student's record view → SF10 entry
  (`/modules/records/sf10_edit.php?student_id=…`), with school year and
  grade level selectors.

Per-subject rows (Filipino, English, Mathematics, Science, Araling
Panlipunan, MAPEH, EPP/TLE, Values Education, etc.) accept four
quarterly grades, a final grade, and remarks.

Steps:

1. Select School Year and Grade Level.
2. Enter quarterly and final grades per subject.
3. Click **Save**.

Expected result (verified): flash
**"SF10 grades saved. General average: 85"** — the system computes the
general average automatically.

### 11.2 SF10 report — VERIFIED WORKING (on screen)

Navigation: **Reports → SF10-JHS Permanent Record → Generate** (with
student, school year, grade level parameters).

Expected result (verified): the permanent record renders with subject
rows, quarterly and final grades, and the computed general average.

**Printing the SF10 — KNOWN BROKEN.** See 15.3.

---

## 12. Enrollment and Section Assignment

### 12.1 Enrollment list — VERIFIED WORKING

Navigation: **Dashboard → Enrollments**. Filter: All / Assigned /
Unassigned; per-year view follows the active school year. Each row has
a **Manage** link to the assignment page.

### 12.2 Section assignment — KNOWN BROKEN

`/modules/enrollment/assign.php` — pick a section from a dropdown
pre-filtered to the enrollment's grade level, click **Save
Assignment**.

**What actually happens (verified):** the save is rejected by CSRF
validation. The browser is redirected to the dashboard with the flash
**"Your session token expired. Please try again."** The enrollment's
section is **not** saved. Retrying produces the same result every time.

**Cause:** the form does not emit the CSRF hidden field its handler
requires (a one-line `csrf_field()` omission in the template).

**Impact:** enrollments cannot be assigned sections through the
browser; the dashboard's "Unassigned Sections" counter will keep
growing. **Requires a software fix** — no user action can work around
it.

---

## 13. Transfers

The transfer module implements DepEd DO 54-2016: school records
(SF10) must be secured within 30 days of a learner's first attendance
at the receiving school. The system auto-computes a 30-day SLA due
date, flags overdue requests, and offers escalation to SGOD.

### 13.1 Transfer list — VERIFIED WORKING

Navigation: **Reports → Transfers**. Direction filter tabs
(All / Incoming / Outgoing), an overdue counter in the policy banner,
and per-row SLA badges and **Manage** links.

**Visual defect (verified):** status badges ("Overdue", "Pending",
"days left") render as white text with no background on the white
table — they are present in the HTML but effectively invisible. The
badge CSS classes are referenced but not defined in any stylesheet.
Status text is still readable in the Status column; the badge layer is
cosmetically broken.

### 13.2 Create transfer request — KNOWN BROKEN

`/modules/transfers/create.php` — student, direction
(incoming/outgoing), counterpart school, request date, first
attendance date, notes; due date is auto-computed.

**What actually happens (verified):** clicking **Create Request**
bounces to the dashboard with **"Your session token expired. Please
try again."** No request is created. Same CSRF-field omission as 12.2.
**Requires a software fix.**

### 13.3 Transfer status updates — KNOWN BROKEN

On a transfer's view page, the **Update Status** form provides:
**Mark Documents Sent**, **Mark SF10 Received**, **Mark Completed**,
**Escalate to SGOD** (registrar, overdue only), and **Reopen (back to
pending)** (registrar, escalated only).

**All of these fail with the same session-token-expired redirect
(verified).** The transfer workflow cannot currently be operated
through the browser. An overdue transfer can be seen on the dashboard
and in notifications, but cannot be actioned. **Requires a software
fix.**

---

## 14. Search

**VERIFIED WORKING** · Who: all signed-in users

Navigation: **Reports → Search** (`/modules/search/index.php`).

1. Type a name or identifier fragment into **Search**.
2. Optionally filter by Status.
3. Click **Search**.

Expected result (verified): matching student rows with links to their
records. Searching "Santos" returned the created student. Malicious
input (`<script>…`) is escaped, not reflected raw.

---

## 15. Reports

Navigation: **Reports** (`/modules/reports/index.php`) — four cards,
each with a **Generate Report** button.

### 15.1 Enrollment Summary — VERIFIED WORKING (on screen)

Per-year enrollment counts by grade level.

### 15.2 Admission Status Report — VERIFIED WORKING (on screen)

Admission counts by status for a selected year.

### 15.3 Student Master List & SF10 — printing KNOWN BROKEN

**Student Master List** and **SF10-JHS Permanent Record** render on
screen (verified — the master list showed all seeded students; SF10
showed entered grades).

**Printing is broken for all report pages** (SF10, Admission Status,
Enrollment Summary, Student Master List, and the print view of a
student record). Runtime print-media verification showed:

- The dark application sidebar and top bar **remain visible** in print
  output.
- The page background stays dark green.
- The `.no-print` class used on action buttons has **no print CSS
  behind it** — no `@media print` rules exist in the loaded
  stylesheets.

**What this means in practice:** using the browser's Print function on
a report page produces a printout of the whole application shell, not a
clean official document. **Do not use browser printing for official
copies until this is fixed.** Workaround for record-keeping: none
within the app; `modules/records/print.php` builds its own clean print
layout (IMPLEMENTED / CODE-VERIFIED, not exercised in the audit).
**Requires a software fix** (print stylesheet).

---

## 16. LIS CSV Export / Import

Registrar only: **Administration → LIS**
(`/modules/admin/lis.php`). CSV columns align with the SF1 School
Register / Enhanced BEEF fields (DepEd DO 35, s. 2022).

### 16.1 LIS export — VERIFIED WORKING

1. Open **Administration → LIS**.
2. Under **Export to LIS CSV**, choose the school year (and grade /
   section filters where offered).
3. Click export.

Expected result (verified): the browser downloads a file such as
`LIS_SF1_TRAC_JHS_20252026_20260923.csv` (804 bytes in the audit's
small dataset). The six-digit EBEIS School ID used in the export comes
from LIS Settings (17.3).

### 16.2 LIS template — VERIFIED WORKING

Download link produces `LIS_SF1_TRAC_JHS_template.csv` with the correct
column headers.

### 16.3 LIS import — VERIFIED WORKING

1. Open **Administration → LIS**.
2. Under import, choose the CSV file.
3. Submit.

Expected result (verified): flash
**"Import complete: 0 created, 2 updated, 0 skipped, 0 errors."** when
re-importing the export — imports match by LRN or Student ID and
update rather than duplicate (idempotent). Import runs are written to
`lis_import_logs` and the audit log.

**Note:** the import form carries a CSRF field; the handler-side CSRF
check for the import action was not separately traced in the audit
(NOT VERIFIED at that level), but the import itself executed
successfully end-to-end.

### 16.4 LIS settings — VERIFIED WORKING

**Administration → Settings → LIS Export Settings**: six-digit EBEIS
School ID (required, validated) and Schools Division Office. Saving is
confirmed by flash message.

---

## 17. School Year and Section Management

Registrar only: **Administration → Settings**
(`/modules/admin/settings.php`). Panels: School Years (list + activate),
Add School Year form, Sections (list), Add Section form, and LIS Export
Settings.

### 17.1 Activate an existing school year — VERIFIED WORKING

Each non-active year row has a **Set Active** button (this form carries
its CSRF token correctly). Switching the active year from the top bar
dropdown (any page) also works — verified; note it redirects to the
Dashboard rather than back to the page you were on.

### 17.2 Add school year — KNOWN BROKEN

Filling label (e.g. "2027-2028"), start/end dates, and clicking **Add
School Year** bounces to the dashboard with **"Your session token
expired. Please try again."** The year is not created. Same CSRF-field
omission. **Requires a software fix.**

### 17.3 Add section — KNOWN BROKEN

Choosing a grade level and section name and clicking **Add** fails with
the same session-token-expired redirect. **Requires a software fix.**

Until fixed, new sections can only be created by direct database
insertion (technical maintainer task).

---

## 18. User Management

Registrar only: **Administration → Users** (`/modules/admin/users.php`).

- **Create User** form: username, full name, password, role
  (registrar/encoder). Code-verified messages: "User {username}
  created.", "Username already exists."
- Per-user **Enable / Disable** toggle ("User status updated.").
- **Clear failed-login records** per user ("Cleared N failed-login
  record(s) for {target}. They can sign in again now.") — resets the
  login throttle for a locked-out account.

**Status: IMPLEMENTED / CODE-VERIFIED.** The page renders and its
messages are sourced from code, but create/disable/throttle-clear were
not exercised in the audit.

---

## 19. Audit Log

**VERIFIED WORKING** · Registrar only: **Administration → Audit**.

Records every significant action with actor, action type, entity,
details, IP, and timestamp. Verified during the audit: entries appeared
for admission create/approve/reject, student creation, SF10 saves,
login failures and throttling, LIS import, backup creation, and restore
attempts. Retention is governed by
`app_settings.audit_retention_days` (default 1825 = 5 years) and the
`purge_old_audit_logs()` function (see `docs/AUDIT-RETENTION.md`).

Filters (entity type, date range, user) and pagination are implemented;
filter behavior was not separately exercised (code-verified).

---

## 20. Notifications

**VERIFIED WORKING (desktop)**

The top-bar bell shows a red dot with the count of overdue transfers.
Clicking the bell opens a dropdown with an **Overdue Transfers** section
(each item links to the transfer's view page) and a **Recent Activity**
section (recent audit events). Verified: 2 items present, links resolved
to the correct pages.

**Mobile touch — POSSIBLE ISSUE (NOT FULLY VERIFIED).** Under touch
emulation, a tap on a notification item sometimes closed the dropdown
without navigating (the tap may re-toggle the dropdown). Mouse clicks
work reliably. If a tap does not navigate on a real phone, tap the bell
again and retry, or navigate via **Reports → Transfers** directly.

---

## 21. Backup and Restore

Registrar only: **Administration → Backup** and **Restore**.

### 21.1 Create backup — VERIFIED WORKING

**Backup → Export Database** writes a logical dump
(`TRUNCATE` + `INSERT` statements, no DDL) to the server's `backups/`
directory and flashes **"Database backup created:
trac_jhs_backup_YYYY-MM-DD_HHMMSS.sql"** (verified — file appeared on
disk, 12–16 KB on the test data). The action is audit-logged.

Note: despite the page copy mentioning a "downloadable SQL file", the
button writes the file **server-side**; it does not trigger a browser
download. `download_backup.php` exists for retrieval, but on the live
Render deployment it has been observed returning 404 (AGENTS.md known
issue) — treat backup download as **NOT VERIFIED** and retrieve files
from the server's `backups/` directory directly.

### 21.2 Restore — KNOWN BROKEN (data-destroying)

**Restore** lists available backups with size and modified time
(verified — listing works), and each row has a **Restore** button behind
a confirmation dialog.

**Do not use Restore in its current state.** Runtime verification showed
that executing a restore **destroys the current data and fails to
restore the backup**, leaving the database truncated:

- What the user sees: flash **"Restore failed: There is no active
  transaction."**
- What actually happens: the dump's own `BEGIN`/`COMMIT` conflicts with
  the handler's outer transaction; the rollback path fails; tables are
  left `TRUNCATE`d with only partial data.
- Underlying defect (reproduced independently of the app): backup dumps
  write tables in **alphabetical order**, so child tables (e.g.
  `academic_records`, which references `students`) are emptied and
  re-inserted **before** their parent tables are repopulated, causing
  foreign-key violations.

**Impact:** a confirmed restore wipes students, admissions, enrollments,
academic records, and audit logs, then reports failure. Recovery is only
possible by re-importing `schema.sql` and re-entering data (or applying
a corrected dump manually via `psql` with `search_path` set).

**Requires a software fix** in both the dump ordering (dependency-safe
order or per-table commits) and the restore handler's transaction
handling. Until fixed, treat backups as export-only archives and
restore by manual `psql` execution by a technical maintainer.

---

## 22. Password Change

**VERIFIED WORKING** · Who: any signed-in user

Navigation: sidebar footer → **Change Password**
(`/modules/account/password.php`).

Fields: Current Password, New Password, Confirm Password.

Verified: submitting with a wrong current password is rejected with
**"Current password is incorrect."** A successful change flashes
"Password updated successfully." (code-verified message; the successful
path was not run in the audit to avoid invalidating the test account).

**Do this first** on any fresh installation for both seed accounts.

---

## 23. Public Pages

**VERIFIED WORKING (content) · inquiry submission KNOWN BROKEN**

Public pages (no login): landing `/`, `/about.php`, `/privacy.php`,
`/terms.php`, `/contact.php`, and `/auth/login.php`. All render with the
public shell; all 18 in-page anchor links on the landing page resolve
(verified).

### Public inquiry form — KNOWN BROKEN

The landing page's **Send Admission Inquiry** form (name, grade,
contact number):

- Client-side validation works: empty fields are blocked with native
  browser validation; invalid contact formats show an error message.
- **Submission does not work.** The button enters a "Sending…" state,
  but the inquiry is never saved. Cause (verified at the network
  level): the page script disables the submit button while the browser
  is serializing the form; disabled controls are excluded from the
  POST, so the server-side gate never fires. The database row count
  does not increase.

**What a visitor experiences:** the button shows "Sending…", then
returns to normal. No success or error message appears. Nothing is
saved. **Requires a software fix.** Until fixed, do not advertise the
online inquiry form; accept inquiries in person, by phone, or by email
(the contact page lists the registrar's email).

---

## 24. Mobile Usage

**VERIFIED WORKING** (tested at 390×844 with touch emulation)

- **Sign-in** works on mobile.
- **Navigation drawer:** the hamburger button opens the sidebar; the
  dark overlay closes it; the **Escape** key closes it; drawer links
  navigate correctly.
- **Layout:** no horizontal page overflow on Dashboard, Admissions, the
  admission form, or the transfer view.
- **Tables:** list tables scroll horizontally inside their wrapper
  (e.g. a 566 px table in a 356 px viewport) — swipe to see all
  columns.
- **Top bar:** school-year dropdown and notification bell are visible
  and usable; action buttons on record pages are reachable.
- **Known limitation:** notification-item taps may close the dropdown
  instead of navigating (see 20). Use the sidebar navigation as the
  reliable path.

---

## 25. Accessibility

Observed during the audit (desktop + keyboard):

- **Works:** login fields are properly labeled and auto-focused;
  sidebar links show a visible 2 px focus outline; the mobile drawer
  is keyboard-operable (Escape closes); `prefers-reduced-motion` is
  respected in the stylesheets; images carry alt text; heading
  structure on the dashboard is logical (H1 brand, H2 page, H3
  sections).
- **Gaps (verified):**
  - No skip-to-content link on authenticated pages.
  - The notification bell has no accessible name (announced as an
    unnamed button by screen readers).
  - Dashboard stat values animate from 0 — screen readers may announce
    the pre-animation value.
  - Most module forms use visual labels not programmatically bound to
    their inputs (no `for=`/`id=` pairing) — screen readers may not
    associate labels with fields.
  - Status badges on tables are invisible (see 13.1) — information is
    duplicated in plain text columns, which is what keeps the tables
    usable.

These are improvement items for a software update, not user-fixable.

---

## 26. Security

How the system protects data — and its limits:

- **Authentication:** username + bcrypt-hashed password. Sessions
  regenerate their ID on login. Session cookie is `HttpOnly`,
  `SameSite=Lax` (no `Secure` flag — correct for the documented
  no-TLS LAN design; would need enabling behind HTTPS).
- **Role-based access control:** every module page calls
  `require_login()`; registrar-only pages call `require_registrar()`.
  Verified: encoder direct-URL access to all six admin pages is
  blocked with "Only the School Registrar can perform this action."
- **CSRF protection:** POST handlers validate a per-session token
  (`require_csrf()`), rejecting forged submissions with HTTP 419 and a
  "Your session token expired." flash. This protection is exactly why
  the broken forms in 12.2, 13.2, 13.3, 17.2, and 17.3 fail — the
  protection works; the forms are missing their token fields.
- **Output escaping:** all dynamic output passes through
  `htmlspecialchars()` (`e()`); an XSS probe (`<script>` in a search
  query) was escaped, not executed (verified).
- **SQL injection:** all queries use PDO prepared statements
  (code-verified; injection-style IDs were harmlessly rejected).
- **Login rate limiting:** repeated failed logins throttle by username
  and IP, using the audit log as the counter; a registrar can clear a
  throttled account from User Management.
- **Audit logging:** every significant action is recorded with actor,
  entity, details, IP, and time (verified).
- **Server hardening:** `.htaccess` blocks direct web access to
  `config/`, `includes/`, `database/`, `backups/` and sets security
  headers (CSP, X-Frame-Options, nosniff, Referrer-Policy, HSTS on
  HTTPS). Note: the PHP built-in dev server **ignores `.htaccess`** —
  these rules apply under Apache (Docker/Render). Two gaps remain:
  `.env*` and `tools/` are not covered by the deny rules.
- **LAN security assumptions:** the design assumes a trusted network;
  traffic is unencrypted. Anyone on the LAN can reach the login page.
  Physically secure the network and rotate credentials if the network
  is shared beyond school staff.
- **TLS:** none built in. For internet exposure, use a reverse proxy
  with TLS (see 4).
- **Password rotation:** change both seed passwords immediately on any
  fresh install (see 3.3). Additionally, credentials were found in the
  repository's git history from an earlier commit; **treat any
  credential that has ever been committed as exposed and rotate it** —
  this includes the database password embedded in an old `.env.local`
  revision. No credentials are reproduced in this guide.

---

## 27. Database Overview

PostgreSQL, schema `trac_jhs_sarms`, 14 tables. A user-level view:

| Table | What it holds |
|---|---|
| `users` | Staff accounts (registrar / encoder), bcrypt hashes, active flag |
| `school_years` | School years (e.g. 2025-2026); exactly one is active |
| `grade_levels` | Grade 7–10 |
| `sections` | Sections per grade level |
| `students` | Student profiles (LRN, name, birthdate, guardian, status) |
| `admissions` | Applications before approval; approving creates a student |
| `enrollments` | A student enrolled in a school year + grade (+ optional section) |
| `academic_records` | Per-year summary: general average, promotional status, attendance, awards |
| `sf10_grade_entries` | Per-subject quarterly/final grades feeding the SF10 |
| `transfer_requests` | Incoming/outgoing SF10 transfers with 30-day SLA fields |
| `app_settings` | Key-value settings (LIS school ID, audit retention) |
| `lis_import_logs` | History of LIS CSV imports |
| `audit_logs` | Who did what, when, from which IP |
| `inquiries` | Public landing-page inquiries (table created by migration 005) |

Relationships in plain terms: approving an **admission** creates a
**student** and an **enrollment**; an enrollment may get a **section**;
each year a student gets an **academic record** and **SF10 grade
entries**; transfers reference students; everything significant lands
in the **audit log**.

---

## 28. Verified User Journeys

Each journey below was executed in a live browser at revision
`a5e19a0`.

### Journey 1 — Public Visitor
Landing → about/privacy/terms/contact → Staff Sign In.
**Result:** all pages render, all anchors resolve, login reachable.
**Status: VERIFIED.** Limitation: inquiry form does not submit (23).

### Journey 2 — Registrar Admission Lifecycle
Create admission → Approve & Enroll → student record → SF10 grades →
SF10 report.
**Result:** every step succeeded; student TRAC-2026-0001 created;
grades saved; report showed the data.
**Status: VERIFIED.** This is the system's primary operational path.

### Journey 3 — Admission Rejection
Create admission → Reject Application.
**Result:** "Application has been rejected." flash; no student created.
**Status: VERIFIED.**

### Journey 4 — Encoder
Login as encoder → sidebar shows no Administration → open data-entry
pages → attempt six admin URLs directly.
**Result:** all six admin URLs blocked with the registrar-only message.
**Status: VERIFIED.** Limitation: encoder-side creation flows not run
as encoder.

### Journey 5 — Mobile
Login → dashboard → drawer navigation → responsive tables → record
pages.
**Result:** no overflow; drawer/overlay/Escape all work; tables scroll.
**Status: VERIFIED.** Limitation: notification tap reliability (20).

### Journey 6 — LIS Round-Trip
Export CSV → download → re-import same file.
**Result:** "Import complete: 0 created, 2 updated, 0 skipped, 0
errors." — idempotent.
**Status: VERIFIED.**

### Journey 7 — Search
Search by student surname → open record.
**Result:** exact match found; record opened.
**Status: VERIFIED.**

### Journey 8 — Reports
Generate master list / SF10 on screen → attempt print.
**Result:** on-screen generation works; print output includes the
application shell.
**Status: DEGRADED — printing broken (15.3).**

### Journey 9 — Error Handling
Invalid IDs (999, "abc", "-1", injection strings), out-of-range page
numbers, XSS payloads.
**Result:** all handled gracefully — redirects with "…not found."
flashes, empty states, escaped output.
**Status: VERIFIED.**

### Journey 10 — Audit Trail
Perform actions (approve, reject, grade save, backup, import) → open
Audit.
**Result:** every action present with actor and timestamp.
**Status: VERIFIED.**

### Journey 11 — Backup & Restore (additional)
Create backup → verify file → list on Restore page → execute restore.
**Result:** backup created and listed; **restore failed with "There is
no active transaction" and left the database truncated.**
**Status: backup VERIFIED; restore KNOWN BROKEN (21.2).**

---

## 29. Feature Verification Matrix

| Feature | Role | Status | Evidence | Limitation |
|---|---|---|---|---|
| Landing page + anchors | public | VERIFIED WORKING | browser audit | — |
| Public inquiry submit | public | KNOWN BROKEN | POST payload captured; DB unchanged | needs JS/form fix |
| Login / logout | all | VERIFIED WORKING | browser audit | — |
| Login error + throttle | all | VERIFIED WORKING | wrong-password test; code | — |
| Dashboard stats | all | VERIFIED WORKING | matched DB truth | count-up animation |
| Admission create | reg+enc | VERIFIED WORKING | browser audit (as registrar) | encoder path untested |
| Admission approve & enroll | reg+enc | VERIFIED WORKING | end-to-end run | encoder path untested |
| Admission reject | reg+enc | VERIFIED WORKING | browser audit | — |
| Admission edit | reg+enc | CODE-VERIFIED | source | not exercised |
| Student list / view | all | VERIFIED WORKING | browser audit | — |
| Student edit | reg | CODE-VERIFIED | source | not exercised |
| Student status change | reg | CODE-VERIFIED | source | not exercised |
| Academic record save | reg | VERIFIED WORKING | saved + flash + DB row | silent validation failure UX (10) |
| SF10 grade entry | reg | VERIFIED WORKING | saved + computed average | — |
| SF10 report (screen) | reg | VERIFIED WORKING | rendered with data | — |
| Report printing | reg | KNOWN BROKEN | print-media audit | needs print CSS |
| Enrollment list | all | VERIFIED WORKING | browser audit | — |
| Section assignment | reg+enc | KNOWN BROKEN | CSRF bounce, verified | needs csrf_field fix |
| Transfer list | all | VERIFIED WORKING | browser audit | badge CSS missing |
| Transfer create | reg+enc | KNOWN BROKEN | CSRF bounce, verified | needs csrf_field fix |
| Transfer status updates | reg+enc | KNOWN BROKEN | CSRF bounce, verified | needs csrf_field fix |
| Search | all | VERIFIED WORKING | browser audit | — |
| Enrollment summary | reg | VERIFIED WORKING | rendered | print broken |
| Admission status report | reg | VERIFIED WORKING | rendered | print broken |
| Student master list | reg | VERIFIED WORKING | rendered with students | print broken |
| Year switcher | reg | VERIFIED WORKING | browser audit | redirects to dashboard |
| Add school year | reg | KNOWN BROKEN | CSRF bounce, verified | needs csrf_field fix |
| Add section | reg | KNOWN BROKEN | CSRF bounce, verified | needs csrf_field fix |
| LIS export | reg | VERIFIED WORKING | file downloaded | — |
| LIS template | reg | VERIFIED WORKING | file downloaded | — |
| LIS import | reg | VERIFIED WORKING | idempotent re-import | handler CSRF not traced |
| LIS settings | reg | VERIFIED WORKING | save confirmed | — |
| User create/disable/throttle-clear | reg | CODE-VERIFIED | source + messages | not exercised |
| Audit log | reg | VERIFIED WORKING | entries for all actions | filters untested |
| Notifications (desktop) | all | VERIFIED WORKING | bell + links | — |
| Notifications (touch) | all | NOT VERIFIED | inconsistent under emulation | possible tap issue |
| Backup create | reg | VERIFIED WORKING | file on disk + flash | server-side only |
| Backup download | reg | NOT VERIFIED | 404 on live (AGENTS.md) | use server files |
| Restore listing | reg | VERIFIED WORKING | files listed | — |
| Restore execution | reg | KNOWN BROKEN | failed + data loss, reproduced | do not use |
| Password change | all | VERIFIED WORKING | validation verified | success path code-verified |
| Encoder restrictions | enc | VERIFIED WORKING | 6 admin URLs blocked | — |
| Mobile layout | all | VERIFIED WORKING | 390 px emulation | notification tap |
| Invalid-ID handling | all | VERIFIED WORKING | 4 ID classes tested | — |
| XSS protection | all | VERIFIED WORKING | probe escaped | — |

---

## 30. Troubleshooting

| Symptom | Likely cause | What to do |
|---|---|---|
| "Your session token expired. Please try again." after clicking Save/Create | You hit one of the known broken forms (section assign, transfer create/update, add year, add section) | **Do not retry repeatedly — it will fail every time.** These require a software fix. Record the action on paper and enter it after the fix. |
| "Invalid credentials or inactive account." | Wrong password, or account disabled, or login throttled | Verify credentials; if throttled, the registrar can clear failed-login records in User Management |
| "Current password is incorrect." | Wrong current password on change-password form | Re-enter the current password |
| Database Unavailable page (503) | PostgreSQL not running | Restart with `bash tools/dev-up.sh`; check `.pglogs/` |
| Empty student list | No students yet, or filters active | Clear filters; the empty state says whether data exists |
| Backup not in Restore list | File not in server `backups/` dir | Check the directory on the server; listing itself works |
| Restore says "There is no active transaction" | Known restore defect | **Stop. Do not retry.** Restore truncates data. See 21.2 — recovery requires schema re-import |
| Report prints with dark sidebar | Known print defect | Do not browser-print official reports yet; see 15.3 |
| LIS import "0 created, N updated" | Expected when re-importing known students | Not an error — imports update by LRN/Student ID |
| LIS import errors listed | Malformed rows (bad LRN, missing fields) | Fix rows in the CSV per the on-screen error list; re-import |
| LAN device cannot connect | Server down, wrong IP, or firewall | Confirm the boot banner URL; allow TCP 8000 on the server firewall |
| "Only the School Registrar can perform this action." | Encoder accessing a registrar page | Expected behavior — ask the registrar |
| Wrong data under a school year | Active school year not set as expected | Switch it in the top-bar dropdown (registrar) — note you land on the Dashboard |
| "SF10 grades saved" but report empty | Viewing report for a different year/grade than the one edited | Re-check the year and grade selectors on both pages |
| Inquiry form "Sending…" then nothing | Known inquiry defect | Do not use the form; record inquiries manually until fixed |

---

## 31. Current System Status

**Verified core registrar workflow:**

> **Admission → Approval → Enrollment → Student Record → SF10 Grade → Report**

This lifecycle is fully operational and was demonstrated end-to-end in
a live browser, including rejection handling, search, on-screen
reports, LIS round-trip, audit logging, and encoder access control.

**Currently known operational blockers (all require software fixes —
no user workaround exists):**

1. **Public inquiry submission** — form never reaches the server gate
   (23).
2. **Section assignment** — CSRF field missing; save always rejected
   (12.2).
3. **Transfer operations** — creation and all status updates rejected
   (13.2, 13.3).
4. **Add school year** — rejected (17.2).
5. **Add section** — rejected (17.3).
6. **Print output** — reports print with the application shell (15.3).
7. **Restore execution** — fails and leaves the database truncated;
   backup→restore cycle must not be used (21.2).
8. **Backup download on live** — 404 observed on Render (21.1).

**Cosmetic/UX defects (functional but degraded):** invisible status
badges (13.1), silent academic-form validation (10), year-switcher
losing page context (17.1), missing label associations and skip link
(25), possible mobile notification tap issue (20).

### Verification Method

Classifications in this guide are based on (a) inspection of the
repository source at revision `a5e19a0` — routes, handlers, forms,
flash messages, schema, deployment artifacts — and (b) a live
runtime/browser audit using PHP + PostgreSQL + Playwright/Chromium
with seeded data, both staff accounts, mobile emulation, keyboard
checks, and error-path testing. No repository files were modified
during the audit.

### Last Verified Revision

`a5e19a0`

Fixes landing after this revision are not reflected here. Before
relying on any KNOWN BROKEN workflow, check the repository for newer
commits or ask the maintainer whether the corresponding fix has been
deployed.

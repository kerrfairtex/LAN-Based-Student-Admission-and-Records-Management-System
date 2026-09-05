# TRAC JHS SARMS

The **TRAC JHS Student Admission and Records Management System** for
Tawi-Tawi Regional Agricultural College Junior High School (TRAC JHS),
Bongao, Tawi-Tawi, BARMM.

The system handles:

- Applicant registration and admission processing
- Student records (profiles, academic history, SF10)
- Enrollment for the active school year
- Transfer requests (incoming / outgoing)
- LIS (Learner Information System) CSV export/import for DepEd reporting
- Database backup and audit log for the registrar

The system is intended for use by the school's **registrar** and
**data encoders**. It is not a public-facing admission portal.

---

## Quick start — local dev (works on any OS)

Requires only PHP 8.1+ and PostgreSQL client tools on `PATH` (no
Composer, no npm, no build step).

```bash
git clone https://github.com/kerrfairtex/LAN-Based-Student-Admission-and-Records-Management-System.git
cd LAN-Based-Student-Admission-and-Records-Management-System
bash tools/dev-up.sh
```

`dev-up.sh` does everything:

1. Verifies PHP + PostgreSQL tools are installed.
2. Creates an embedded PostgreSQL cluster in `.pgdata/` (gitignored).
3. Starts it on port 5433.
4. Imports `database/schema.sql` (14 tables, 2 seed accounts).
5. Starts PHP's built-in dev server on port 8000.
6. Prints the URLs you can sign in at.

When the boot banner appears, open either URL in your browser:

```
TRAC JHS SARMS is live.
  Loopback : http://127.0.0.1:8000/
  LAN      : http://192.168.x.x:8000/        ← any device on your LAN
```

(On sandboxed Termux/Android, the LAN line may show 127.0.0.1 because
the environment has no netlink privileges. Run on a normal Linux
server, macOS, or WSL to get a real LAN IP.)

### Seed accounts

Two accounts are committed to `database/schema.sql` so a fresh clone is
immediately usable:

| Username   | Password         | Role         | Access                          |
|------------|------------------|--------------|---------------------------------|
| `registrar`| `Registrar@2026` | registrar    | Full (admit, enroll, backup)    |
| `encoder`  | `Encoder@2026`   | encoder      | Data entry only                 |

**Change both passwords immediately** under **Account → Change Password**
after first sign-in. The repo is public on GitHub — anyone who clones
gets the same bcrypt hashes.

### Stopping the dev server

Press **Ctrl+C** in the terminal where `dev-up.sh` is running. The
shutdown handler stops the embedded PostgreSQL child cleanly.

### Re-running

`dev-up.sh` is idempotent. If `.pgdata/` already exists with a matching
PostgreSQL major version, it just starts the cluster and re-imports
schema (which is itself idempotent — every `CREATE` uses `IF NOT EXISTS`).

---

## LAN access

The dev server binds to `0.0.0.0:8000` (all interfaces). Any device on
the same network can sign in at the LAN URL printed at boot.

For shared-internet access (e.g. registrar at home, encoder at a
different branch), port-forward port 8000 on your router to the host.
**There is no built-in TLS** — the system is for use on a trusted LAN.
Run behind a reverse proxy (nginx, Caddy) with Let's Encrypt if you need
HTTPS from the public internet.

---

## Live deployment

- **URL:** https://trac-jhs-sarms.onrender.com
- **Stack:** PHP 8.3 + PostgreSQL 18, deployed on Render via Docker
- **Branch:** `main` (auto-deploy on push)

See [`AGENTS.md`](AGENTS.md) for the Render service ID, deploy commands,
and operational notes (audit retention, migrations, persistent disk
layout).

---

## Tech stack

| Layer       | Tech                                                                |
|-------------|---------------------------------------------------------------------|
| Frontend    | Plain HTML + hand-rolled CSS + `assets/js/site.js` + Bootstrap 5.3.3 + Bootstrap Icons 1.11.3 + Google Fonts (Fraunces, Inter) |
| Backend     | PHP 8.x (PDO), no framework                                        |
| Database    | PostgreSQL 18 with `pgcrypto`                                       |
| Deploy      | Docker image (`php:8.3-apache` + `pdo_pgsql`), Render.com            |
| Persistence | Render persistent disk at `/var/www/html/storage` (sessions, uploads, backups) |

---

## Modules

| Module                              | Path                                          |
|-------------------------------------|-----------------------------------------------|
| Authentication, RBAC, CSRF          | `auth/login.php`, `auth/logout.php`, `includes/auth.php`, `includes/csrf.php` |
| Dashboard + 5 stat-cards             | `dashboard.php`                                |
| Admission                           | `modules/admission/` (index, create, edit, view) |
| Student records                     | `modules/records/` (index, view, edit, academic, print, sf10_edit, status) |
| Enrollment / section assignment     | `modules/enrollment/` (index, assign)         |
| Transfers (SF10 in/out)             | `modules/transfers/` (index, create, view)    |
| Search                              | `modules/search/index.php`                     |
| Reports                             | `modules/reports/` (index, admission_status, enrollment_summary, sf10, student_masterlist) |
| Admin                               | `modules/admin/` (users, audit, backup, download_backup, restore, settings, lis, lis_export, lis_import, lis_template, set_year) |
| Account (change password)           | `modules/account/password.php`                 |
| Public landing                      | `index.php` (loads `templates/landing.html`)   |
| Public pages                        | `about.php`, `privacy.php`, `terms.php`, `contact.php` |

---

## Database schema

Canonical schema: `database/schema.sql` (PostgreSQL). It creates **14
tables** under the `trac_jhs_sarms` schema:

`users`, `school_years`, `grade_levels`, `sections`, `students`,
`admissions`, `enrollments`, `academic_records`, `sf10_grade_entries`,
`transfer_requests`, `app_settings`, `lis_import_logs`, `audit_logs`,
`inquiries`.

The files under `database/migrations/` are upgrade paths for older
MySQL installs. **Do NOT run them on a fresh Postgres install.**

- `002_phase2.sql`, `003_lis_csv.sql` — authored against MySQL
  (`ENGINE=InnoDB`, `TIMESTAMP`, `USE`); they will error on Postgres.
- `004_audit_retention.sql` onward — written for PostgreSQL.

---

## LIS CSV Export / Import

Registrar → **Admin → LIS CSV** in the sidebar:

- **Export:** Download enrolled learners as SF1-aligned CSV (filter by
  school year, grade, section)
- **Import:** Upload CSV to create/update students; matches by LRN or
  Student ID
- **Template:** Download a sample CSV with the correct column headers
- **Settings:** Configure the 6-digit EBEIS School ID in System Settings

CSV columns align with the SF1 School Register and Enhanced BEEF fields
(DepEd DO 35, s. 2022). Reference schema is in
`database/migrations/003_lis_csv.sql`.

---

## Public landing design

The landing page (`index.php`) renders `templates/landing.html` as a
string, applying 5 surgical modifications (CSRF field on the inquiry
form, button attrs, repointed Staff Sign In links). The template is
the design source of truth — if you change the landing's look, edit
the template, not the PHP.

---

## License

Internal use for TRAC JHS administrative operations.
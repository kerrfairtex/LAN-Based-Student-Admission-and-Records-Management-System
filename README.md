# TRAC JHS SARMS

The **LAN-Based Student Admission and Records Management System** for
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

## Live deployment

- **URL:** https://trac-jhs-sarms.onrender.com
- **Stack:** PHP 8 + PostgreSQL, deployed on Render via Docker
- **Branch:** `main` (auto-deploy on push)

See [`AGENTS.md`](AGENTS.md) for the Render service ID, deploy commands, and
operational notes.

---

## Local development (Termux)

There is no Composer or npm step. PHP files are served directly.

### 1. Start the local PostgreSQL cluster

The repo ships with a local cluster in `.pgdata/` (gitignored):

```bash
cd ~/LAN-Based-Student-Admission-and-Records-Management-System
pg_ctl -D .pgdata -l .pglogs/server.log start
```

If `.pgdata/` is empty (fresh clone), initialize and import the schema:

```bash
initdb -D .pgdata -U postgres --auth=trust --no-locale -E UTF8
# edit .pgdata/postgresql.conf: set port = 6543, listen_addresses = '127.0.0.1'
# edit .pgdata/pg_hba.conf: add 'host all all 127.0.0.1/32 trust'
pg_ctl -D .pgdata -l .pglogs/server.log start
PGOPTIONS='-c search_path=trac_jhs_sarms,public' \
    psql -h 127.0.0.1 -p 6543 -U postgres -d postgres \
    -v ON_ERROR_STOP=1 -f database/schema.sql
```

### 2. Run the PHP dev server

```bash
export DB_HOST=127.0.0.1 DB_PORT=6543 DB_NAME=postgres \
       DB_USER=postgres DB_PASS= DB_SCHEMA=trac_jhs_sarms
php -S 127.0.0.1:8000
```

Open http://127.0.0.1:8000/.

The PHP built-in server does NOT enforce `.htaccess` deny rules — sensitive
directories are readable in local dev. Fine for development; do not rely
on that locally.

---

## First deploy

`database/schema.sql` ships with bcrypt-hashed seed accounts whose plaintext
passwords are not committed to this repository. The deploying operator must
rotate every seed account through **Account → Change Password** immediately
after the first successful sign-in. Until rotation, the live system is
vulnerable to any reader of the public GitHub repository.

---

## Tech stack

| Layer       | Tech                                                                |
|-------------|---------------------------------------------------------------------|
| Frontend    | Plain HTML + hand-rolled CSS + a small `assets/js/site.js`           |
| Backend     | PHP 8.x (PDO), no framework                                        |
| Database    | PostgreSQL 18+ with `pgcrypto`                                     |
| Deploy      | Docker image (`php:8.3-apache` + `pdo_pgsql`), Render.com            |
| Persistence | Render persistent disk at `/var/www/html/storage` (sessions, uploads, backups) |

---

## Modules

| Module                              | Path                                          |
|-------------------------------------|-----------------------------------------------|
| Authentication, RBAC                 | `auth/login.php`, `includes/auth.php`          |
| Dashboard + stats                   | `dashboard.php`                                |
| Admission                           | `modules/admission/`                           |
| Student records                     | `modules/records/`                             |
| Enrollment / section assignment     | `modules/enrollment/`                          |
| Transfers (SF10 in/out)             | `modules/transfers/`                           |
| Search                              | `modules/search/index.php`                     |
| Reports (admission, enrollment, SF10)| `modules/reports/`                             |
| Admin (users, audit, backup, settings, LIS) | `modules/admin/`                      |
| Account (change password)           | `modules/account/`                             |
| Public landing                      | `index.php` (loads `templates/landing.html`)   |
| Public pages (about/privacy/terms/contact) | `about.php`, `privacy.php`, `terms.php`, `contact.php` |

---

## LIS CSV Export / Import

Registrar → **Admin → LIS CSV** in the sidebar:

- **Export:** Download enrolled learners as SF1-aligned CSV (filter by school
  year, grade, section)
- **Import:** Upload CSV to create/update students; matches by LRN or Student ID
- **Template:** Download a sample CSV with the correct column headers
- **Settings:** Configure the 6-digit EBEIS School ID in System Settings

CSV columns align with the SF1 School Register and Enhanced BEEF fields
(DepEd DO 35, s. 2022). Reference schema is in `database/migrations/003_lis_csv.sql`.

---

## Database schema

The canonical schema is `database/schema.sql` (PostgreSQL, includes all13
tables for admission, records, enrollment, transfers, audit, LIS). The
files under `database/migrations/` are upgrade paths for older MySQL installs —
do NOT run them on a fresh install.

Tables: `users`, `school_years`, `grade_levels`, `sections`, `students`,
`admissions`, `enrollments`, `academic_records`, `sf10_grade_entries`,
`transfer_requests`, `app_settings`, `lis_import_logs`, `audit_logs`.

---

## Public landing design

The landing page (`index.php`) renders `templates/landing.html` as a string,
applying5 surgical modifications (CSRF field on the inquiry form, button
attrs, repointed Staff Sign In links). The template is the design source of
truth — if you change the landing's look, edit the template, not the PHP.

---

## License

Internal use for TRAC JHS administrative operations.